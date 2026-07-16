# Google "Sign in with Google" setup

The admin and officer account-creation forms, and the public landing page's
**Sign Up with Google** button, share one optional **Sign in with Google**
popup that pre-fills the applicant's name and email and links their Google
account (`google_id`). It runs a popup OAuth flow that **never logs anyone
in** — on the admin forms the signed-in SuperAdmin/Admin keeps their own
session; on the landing page the visitor stays signed out and is handed off
to the officer registration wizard (`/signup`) with their name/email
pre-filled.

The feature stays **inert** until the three `.env` keys below are filled: the
button will just report "Google sign-in is not configured yet." No code changes
are needed to enable it — only the manual Google Cloud steps here.

---

## 1. Create a Google OAuth 2.0 Client

These steps happen in the [Google Cloud Console](https://console.cloud.google.com/)
and can only be done by someone with access to the project's Google account.

1. Select or create a project (top bar → project picker).
2. Go to **APIs & Services → OAuth consent screen**.
   - User type: **External** (unless everyone is in one Google Workspace).
   - Fill the app name, support email, and developer contact email.
   - While the app is in **Testing**, add each admin/officer Google address under
     **Test users** — only listed users can sign in until the app is published.
3. Go to **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
   - Application type: **Web application**.
   - Name it anything (e.g. "SO-Connect account linking").
   - Under **Authorized redirect URIs**, add the callback URL(s) — see below.
4. Click **Create**. Copy the **Client ID** and **Client secret**.

## 2. Authorized redirect URIs to register

Register the exact callback URL, matching `GOOGLE_REDIRECT_URI`. The admin and
officer forms share one callback, so a single URI is enough:

```
http://localhost/admin/accounts/google/callback          (local, plain)
http://127.0.0.1:8000/admin/accounts/google/callback     (local, artisan serve)
https://YOUR-DOMAIN/admin/accounts/google/callback        (production)
```

Add every origin you actually use (Google matches these **exactly** — scheme,
host, and port must all match). Use the same value in `GOOGLE_REDIRECT_URI`.

## 3. Fill the `.env` keys

Copy the keys from `.env.example` into your `.env` and paste the values from
step 1:

```dotenv
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI="${APP_URL}/admin/accounts/google/callback"
```

`GOOGLE_REDIRECT_URI` defaults to `${APP_URL}/admin/accounts/google/callback`.
Make sure `APP_URL` matches one of the redirect URIs registered in step 2, or
set `GOOGLE_REDIRECT_URI` to the full URL explicitly.

Then clear the config cache:

```bash
php artisan config:clear
```

## 4. Test

**Admin/officer creation forms:**
1. Sign in as a SuperAdmin and open **Create Admin Account** (or as an Admin,
   **Create Officer**).
2. Click **Sign in with Google** — a popup opens.
3. Have the applicant authorize with a Google account listed under **Test users**.
4. The popup closes and the form's first name, last name, and email fill in;
   a hidden `google_id` is captured. Submit to create the linked account
   (its email is marked verified because Google vouched for it).

**Public landing page:**
1. Sign out (or use a private window) and open the landing page's **Login /
   Sign Up** section.
2. Click **Sign Up with Google** — a popup opens.
3. Authorize with a Google account listed under **Test users**.
4. The popup closes and the browser navigates to the officer registration
   wizard (`/signup`) with the Google name/email carried over as query
   params. Complete Step 1 (ID scan) — Step 2's name/email fields and a
   hidden `google_id` are pre-filled. The linked account is created once a
   SuperAdmin/Admin approves the resulting request.

---

## Notes

- **No SuperAdmin logout:** the callback is stateless and never calls
  `Auth::login()` for the Google user — it only reads their public profile and
  posts it back to the opener window.
- **Publishing:** to allow any Google user (not just test users), submit the
  OAuth consent screen for verification in the Google Cloud Console. For an
  internal tool, keeping it in Testing with a maintained test-user list is fine.
- **Scopes used:** `openid`, `profile`, `email` — read-only profile basics.
