# Single sign-on with Google Workspace

Luminara CRM signs people in through any OpenID Connect provider. This guide sets it up for one business that uses Google Workspace. Each business enters its own provider; nothing is shared between businesses.

How it works for a user: they choose **Sign in with single sign-on**, type their work email, sign in on Google's page, and land in the CRM. Only people who already have a CRM user in that business can sign in this way. Add them in **Settings > Users** first.

## 1. Switch the feature on for the install (operator, once)

In `.env.prod` set `LUMINARA_SSO=true` and recreate `app`, `worker` and `scheduler`. The sign-in page then shows the single sign-on link.

## 2. Create the OAuth client in Google (a Google Workspace admin of the business)

1. Open console.cloud.google.com and create (or pick) a project for the CRM.
2. **APIs & Services > OAuth consent screen**:
   - User type: **Internal**. Only accounts in your Google Workspace can then sign in. This is the most important setting.
   - App name "Luminara CRM", your support email, and the scopes `openid`, `email` and `profile`.
3. **APIs & Services > Credentials > Create credentials > OAuth client ID**:
   - Application type: **Web application**.
   - Authorized redirect URI: `https://<your CRM address>/admin/sso/callback`, for example `https://crm.luminara.digital/admin/sso/callback`.
4. Copy the **Client ID** and **Client secret**.

## 3. Enter the settings in the CRM (an administrator of the business)

**Settings > Configuration > Single sign-on > OpenID Connect**:

| Field | Value |
| --- | --- |
| Turn on single sign-on | On |
| Provider address (issuer) | `https://accounts.google.com` |
| Client ID / Client secret | From step 2 (the secret is stored encrypted) |
| Email domains | Your Google Workspace domain(s), for example `example.com` |
| Require single sign-on for these domains | Optional. Turn it on once single sign-on works. Keep one administrator account **outside** these domains so you can still get in if Google is unavailable or the settings are wrong. |
| The provider enforces two-factor sign-in | Turn on only if your Google Workspace enforces 2-Step Verification for everyone (Admin console > Security > 2-Step Verification > Enforcement). Otherwise the CRM still asks for its own second factor. |

## 4. Test

Sign out, choose **Sign in with single sign-on**, and enter a work email. Every attempt, and its outcome, is in **Settings > Security audit log** as `auth.sso_login` or `auth.sso_failed`; the failure reason is recorded there.

## What the CRM checks

- **Sign-in flow:** the authorization-code flow with PKCE, a one-time `state` (expires after 10 minutes, usable once) and a `nonce`.
- **The ID token:**
  - It is signed by one of Google's published keys.
  - Its issuer is `https://accounts.google.com`.
  - It was issued to your client ID.
  - It has not expired, and its nonce matches this sign-in.
- **The account:**
  - The email is verified.
  - It belongs to one of your domains, and Google's `hd` (hosted domain) claim matches, so personal Gmail accounts are refused.
  - It is an active CRM user of this same business.
- **Network safety:** the provider address must be HTTPS on a public address, and the server pins its connection to the address it checked.

Other providers (Microsoft Entra ID, Okta, Keycloak) work the same way. Use their issuer address, for example `https://login.microsoftonline.com/<tenant id>/v2.0` for Entra ID.
