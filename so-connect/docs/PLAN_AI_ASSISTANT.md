# Student Connect Assistant Architecture

The in-app assistant is a read-only Gemini-powered guide for Student Connect.
It answers dashboard workflow questions and recommends only internal pages the
current user is authorized to access.

```
Browser chat widget -> POST /assistant/chat -> AssistantController
                                              -> AssistantKnowledgeBase
                                              -> GeminiClient -> Google Gemini API
                                              -> route-token validation -> safe page chips
```

`AssistantKnowledgeBase` builds the page index from the role-aware menu and
reachable routes. `AssistantController` gives that index to Gemini and accepts
only `[[route:<route-name>]]` references from the generated response.
`extractLinks()` resolves each token against the same per-user index, so an
invented, ambiguous, or unauthorized route cannot become a clickable link.

The browser request/response format and chat UI are intentionally provider
independent. `GeminiClient` maps the conversation to Gemini's native content
format and keeps `GEMINI_API_KEY` server-side. The assistant does not use
Google Search grounding, database access, external links, or action tools.

See `docs/assistant-contract.md` for the wire and link-safety contract, and
`docs/GEMINI-API-SETUP.md` for manual API-key configuration.
