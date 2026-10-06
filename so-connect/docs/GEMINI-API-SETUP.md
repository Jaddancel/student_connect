# Gemini API Setup

The Student Connect assistant uses the Google Gemini API from the Laravel
server. The API key must stay in the server environment; it must never be
committed, sent to the browser, or stored in a `VITE_*` variable.

## Create an API key

1. Sign in to [Google AI Studio](https://aistudio.google.com/apikey).
2. Create or select a Google project, then create a Gemini API key for it.
3. Confirm that the project and deployment region are eligible for the Gemini
   free tier. Review the current limits in [AI Studio usage](https://aistudio.google.com/usage)
   and Google's [Gemini API pricing](https://ai.google.dev/gemini-api/docs/pricing).
4. Restrict and rotate the key according to your organization's Google Cloud
   security policy. Do not paste the key into source code, tickets, or chat.

## Configure Student Connect

Add the key to the ignored `so-connect/.env` file or your deployment's secret
manager:

```dotenv
GEMINI_API_KEY=your-api-key
GEMINI_API_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_TIMEOUT=30
GEMINI_MODEL=gemini-3.8-flash
```

`GEMINI_MODEL` is configurable because model availability can vary by project
and change over time. Use a Gemini text model available to your project; see
the [Gemini model guide](https://ai.google.dev/gemini-api/docs/models).

Clear Laravel's cached configuration after changing the environment, then
restart the application:

```bash
cd so-connect
php artisan config:clear
docker compose up -d --remove-orphans
```

## Verify

Sign in to Student Connect and ask the existing assistant how to complete a
dashboard task. A successful answer can include chips that link only to pages
the signed-in user is authorized to open. Test with an admin and an
officer/member account to confirm inaccessible pages are never linked.

Free-tier quota, unavailable-model, authentication, and network errors use the
assistant's existing generic unavailable state. Provider details and API keys
are not returned to the browser.

## Retired OLLAMA data

The Compose service and configuration for OLLAMA have been removed. After
confirming Gemini works, an operator may reclaim its old local Docker volume
manually. List volumes, identify the OLLAMA volume, and remove only that volume:

```bash
docker volume ls
docker volume rm <ollama-volume-name>
```

Do not remove a volume until the old chatbot is no longer needed; volume removal
is permanent.
