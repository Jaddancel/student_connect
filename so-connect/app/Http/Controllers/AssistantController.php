<?php

namespace App\Http\Controllers;

use App\Services\AssistantKnowledgeBase;
use App\Services\LlmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * In-app "how do I…" assistant. Read-only: explains dashboard features and
 * workflows, grounded in {@see AssistantKnowledgeBase}'s role-filtered page
 * index, and deep-links via `[[route:…]]` tokens the model is instructed to
 * emit — resolved (or dropped) against that same index so a hallucinated or
 * out-of-role route name can never surface as a link. See
 * docs/assistant-contract.md for the wire shape.
 */
class AssistantController extends Controller
{
    public function chat(Request $request, LlmClient $llm, AssistantKnowledgeBase $kb): JsonResponse
    {
        // Mirrors IdScanController: this endpoint is consumed exclusively by
        // fetch(), which can't follow the redirect Laravel issues for a
        // failed validation on a non-JSON-flagged request.
        try {
            $validated = $request->validate([
                'messages' => ['present', 'array', 'max:20'],
                'messages.*.role' => ['required', 'string', 'in:user,assistant'],
                'messages.*.content' => ['required', 'string', 'max:2000'],
                'current_path' => ['nullable', 'string', 'max:2048'],
                'opening' => ['nullable', 'boolean'],
            ]);
        } catch (ValidationException) {
            return $this->degraded('invalid request');
        }

        $index = $kb->forUser($request->user());
        if ($index['pages'] === []) {
            return $this->degraded('assistant unavailable');
        }

        $opening = $validated['opening'] ?? false;
        $systemPrompt = $this->buildSystemPrompt($index, $validated['current_path'] ?? null, $opening);
        $messages = array_map(
            fn (array $m) => ['role' => $m['role'], 'content' => $m['content']],
            $validated['messages'],
        );
        if ($opening) {
            $messages[] = ['role' => 'user', 'content' => 'Open the chat now.'];
        }

        $result = $llm->chat($messages, $systemPrompt);
        if (! $result['ok']) {
            return $this->degraded($result['note'] ?? 'assistant unavailable');
        }

        [$reply, $suggestions] = $opening
            ? $this->extractSuggestions($result['reply'])
            : [$result['reply'], []];
        [$reply, $links] = $this->extractLinks($reply, $index['pages']);
        if ($opening) {
            $links = [];
        }

        // `ok: true` promises something to render. A completion that was empty
        // to begin with, or that was nothing but route tokens, would otherwise
        // reach the panel as a blank bubble — so it stands on its links if it
        // has any, and degrades if it doesn't.
        if ($reply === '') {
                if ($links === [] && $suggestions === []) {
                return $this->degraded('empty reply');
            }

                if ($links !== []) {
                    $reply = 'Here’s the page for that:';
                }
        }

        return response()->json(['ok' => true, 'reply' => $reply, 'links' => $links, 'suggestions' => $suggestions]);
    }

    private function degraded(string $note): JsonResponse
    {
        return response()->json(['ok' => false, 'reply' => '', 'links' => [], 'suggestions' => [], 'note' => $note]);
    }

    /**
     * @param  array{pages: array<int,array{name:string,path:string,route:?string,group:string,description:string,keywords:string}>, workflows: string}  $index
     */
    private function buildSystemPrompt(array $index, ?string $currentPath, bool $opening = false): string
    {
        $grouped = [];
        foreach ($index['pages'] as $page) {
            $grouped[$page['group']][] = $page;
        }

        $lines = [];
        foreach ($grouped as $group => $pages) {
            $lines[] = "## {$group}";
            foreach ($pages as $page) {
                $line = "- {$page['name']} — {$page['path']}";
                if ($page['route'] !== null) {
                    $line .= " — [[route:{$page['route']}]]";
                }
                if ($page['description'] !== '') {
                    $line .= " — {$page['description']}";
                }
                $lines[] = $line;
            }
        }
        $pagesSection = implode("\n", $lines);
        $currentPathLabel = $currentPath !== null && $currentPath !== '' ? $currentPath : 'unknown';
        $openingInstructions = $opening
            ? "\nThe chat has already greeted the user. Reply only with exactly three helpful next questions as separate [[suggestion:question]] tokens. Make each question concise, actionable, and relevant to what a user is likely trying to do on the current page. Do not use route tokens, bullet lists, or any other text.\n"
            : '';

        return <<<PROMPT
        You are the in-app assistant for Student Connect, a student-organization
        management dashboard. You explain how the dashboard works and point users to
        the right page. You are strictly read-only: you cannot query the database,
        cannot see any user's private data, and cannot perform actions on anyone's
        behalf.

        Mirror the user's language: reply in English to an English question, and in
        Filipino to a Filipino or Taglish question.

        The user is currently on: {$currentPathLabel}
        {$openingInstructions}

        Pages this user can reach — ONLY reference pages from this list. Never invent,
        guess, or infer a page or route name that isn't shown here.
        {$pagesSection}

        {$index['workflows']}

        Write in Markdown — it is rendered before the user sees it, so the syntax
        itself never shows. Use **bold** for a UI label the user has to find or
        click, a numbered list for steps that run in order, hyphen bullets for
        points that don't, and `backticks` for a path, field key, or value typed
        literally. Keep it light: the chat panel is narrow, so favour short
        paragraphs over headings, skip tables entirely, and save fenced code blocks
        for genuinely multi-line snippets.

        When recommending a page, cite it with a token in the exact form
        [[route:<route-name>]], using only a route name shown in brackets above. Do
        not write raw URLs or markdown links — the token becomes a button the user
        can click, while a link you write yourself is stripped down to its text. If
        nothing above fits the question, say so plainly instead of guessing.
        PROMPT;
    }

    /**
     * Pulls model-authored opening prompts out of the reply so the browser can
     * render them as clickable chips instead of exposing protocol text.
     *
     * @return array{0:string,1:array<int,string>}
     */
    private function extractSuggestions(string $reply): array
    {
        $suggestions = [];
        $clean = preg_replace_callback('/\[\[suggestion:([^\]\r\n]{1,160})\]\]/', function (array $match) use (&$suggestions): string {
            $suggestion = trim($match[1]);
            if (str_ends_with($suggestion, '?') && count($suggestions) < 3 && ! in_array($suggestion, $suggestions, true)) {
                $suggestions[] = $suggestion;
            }

            return '';
        }, $reply) ?? $reply;

        if ($suggestions === []) {
            $clean = preg_replace_callback('/^\s*[-*]\s+(.+\?)\s*$/m', function (array $match) use (&$suggestions): string {
                $suggestion = trim($match[1]);
                if (count($suggestions) < 3 && ! in_array($suggestion, $suggestions, true)) {
                    $suggestions[] = $suggestion;
                }

                return '';
            }, $clean) ?? $clean;
        }

        if ($suggestions === []) {
            $clean = preg_replace_callback('/[^?\r\n]{3,160}\?/', function (array $match) use (&$suggestions): string {
                $suggestion = trim($match[0]);
                if (count($suggestions) < 3 && ! in_array($suggestion, $suggestions, true)) {
                    $suggestions[] = $suggestion;
                }

                return '';
            }, $clean) ?? $clean;
        }

        return [trim($clean), $suggestions];
    }

    /**
     * Strips `[[route:…]]` tokens from the reply and resolves each against
     * the user's permitted index. A token whose route name doesn't appear in
     * the index — hallucinated, out-of-role, or ambiguous across multiple
     * concrete pages sharing one route — is dropped silently rather than
     * surfaced as a broken or unauthorized link.
     *
     * @param  array<int,array{name:string,path:string,route:?string,group:string,description:string,keywords:string}>  $pages
     * @return array{0:string,1:array<int,array{name:string,path:string}>}
     */
    private function extractLinks(string $reply, array $pages): array
    {
        $routeCounts = [];
        foreach ($pages as $page) {
            if ($page['route'] !== null) {
                $routeCounts[$page['route']] = ($routeCounts[$page['route']] ?? 0) + 1;
            }
        }

        $byRoute = [];
        foreach ($pages as $page) {
            if ($page['route'] !== null && $routeCounts[$page['route']] === 1) {
                $byRoute[$page['route']] = $page;
            }
        }

        $links = [];
        $seen = [];
        $clean = preg_replace_callback('/\[\[route:([a-zA-Z0-9_.\-]+)\]\]/', function (array $m) use ($byRoute, &$links, &$seen) {
            if (! isset($byRoute[$m[1]]) || isset($seen[$m[1]])) {
                return '';
            }
            $seen[$m[1]] = true;
            $links[] = ['name' => $byRoute[$m[1]]['name'], 'path' => $byRoute[$m[1]]['path']];

            return '';
        }, $reply) ?? $reply;

        // Collapse whitespace left behind by stripped tokens — mid-line only.
        // Leading indentation is structural in the Markdown reply (it's what
        // nests a sub-list), so it has to survive to reach the renderer.
        $clean = preg_replace('/(?<=\S) {2,}/', ' ', $clean) ?? $clean;
        $clean = preg_replace('/[ \t]+\n/', "\n", $clean) ?? $clean;
        $clean = preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean;

        return [trim($clean), $links];
    }
}
