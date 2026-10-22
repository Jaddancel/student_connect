<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentCategory;
use App\Models\Form;
use App\Models\ReportTemplate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateGalleryController extends Controller
{
    public function index(Request $request): View
    {
        $categoryId = $request->filled('category') ? $request->integer('category') : null;
        $search = trim((string) $request->query('q', ''));
        $type = in_array($request->query('type'), ['form', 'report'], true)
            ? (string) $request->query('type')
            : '';

        $forms = collect();
        if ($type !== 'report') {
            $forms = Form::query()
                ->with(['category', 'activeTemplates:id,form_id,template_name,version,slot_order'])
                ->withCount('fields')
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description_text', 'like', '%'.$search.'%')
                    ->orWhere('route_name', 'like', '%'.$search.'%')))
                ->orderBy('name')
                ->get();
        }

        $reports = collect();
        if ($type !== 'form') {
            $reports = ReportTemplate::query()
                ->with(['category', 'activeTemplates:id,report_template_id,template_name,version,slot_order'])
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')))
                ->orderBy('name')
                ->get();
        }

        $items = $forms->map(fn (Form $form) => [
            'id' => (int) $form->getKey(),
            'type' => 'form',
            'name' => (string) $form->name,
            'description' => (string) ($form->description_text ?? ''),
            'category_id' => $form->category_id ? (int) $form->category_id : null,
            'category' => $form->category?->name ?? 'Uncategorized',
            'category_slug' => $form->category?->slug ?? 'uncategorized',
            'active' => (bool) $form->is_active,
            'published' => (bool) $form->is_published,
            'metric_label' => 'fields',
            'metric' => (int) $form->fields_count,
            'templates' => $form->activeTemplates->map(fn ($template) => [
                'name' => (string) ($template->template_name ?: 'Printed template'),
                'version' => (int) $template->version,
            ])->when(
                $form->activeTemplates->isEmpty() && trim((string) (($form->pdf_template ?? [])['html'] ?? '')) !== '',
                fn ($templates) => $templates->push(['name' => 'Legacy printable layout', 'version' => 1]),
            )->all(),
            'preview_url' => route('admin.form-builder.preview', $form),
            'edit_url' => route('admin.form-builder.edit', $form),
            // Admins author forms but do not submit them; the preview is the
            // correct non-mutating gallery action for a form card.
            'use_url' => null,
        ])->concat($reports->map(fn (ReportTemplate $report) => [
            'id' => (int) $report->getKey(),
            'type' => 'report',
            'name' => (string) $report->name,
            'description' => (string) ($report->description ?? ''),
            'category_id' => $report->category_id ? (int) $report->category_id : null,
            'category' => $report->category?->name ?? 'Uncategorized',
            'category_slug' => $report->category?->slug ?? 'uncategorized',
            'active' => (bool) $report->is_active,
            'published' => (bool) $report->is_active,
            'metric_label' => 'data tokens',
            'metric' => count($report->tokens()),
            'templates' => $report->activeTemplates->map(fn ($template) => [
                'name' => (string) ($template->template_name ?: 'Printed template'),
                'version' => (int) $template->version,
            ])->all(),
            'preview_url' => null,
            'edit_url' => route('admin.report-templates.edit', $report),
            'use_url' => $report->is_active ? route('reports.index', ['template' => $report->getKey()]) : null,
        ]))->sortBy(fn ($item) => $item['category'].' '.$item['name'])->values();

        $categories = ContentCategory::query()
            ->withCount(['forms', 'reportTemplates'])
            ->ordered()
            ->get()
            ->each(fn (ContentCategory $category) => $category->setAttribute(
                'gallery_count',
                (int) $category->forms_count + (int) $category->report_templates_count,
            ));

        return view('pages.admin.template-gallery.index', [
            'title' => 'Template Gallery',
            'items' => $items,
            'categories' => $categories,
            'categoryFilter' => $categoryId ? (string) $categoryId : '',
            'search' => $search,
            'typeFilter' => $type,
        ]);
    }
}
