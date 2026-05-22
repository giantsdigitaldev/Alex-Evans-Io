# X (Twitter) API credentials for AE SEO Writer

To use **Post to X** and **Schedule 4 daily posts** from the plugin, you need to connect your [@alexevans_io](https://x.com/alexevans_io) account via the X API. The plugin uses **OAuth 1.0a** to post tweets.

## What you need (4 values)

| Setting in WordPress | Where to get it |
|----------------------|-----------------|
| **X (Twitter) API Key** | Consumer Key (API Key) from your app |
| **X (Twitter) API Secret** | Consumer Secret from your app |
| **X (Twitter) Access Token** | OAuth 1.0a Access Token for the user @alexevans_io |
| **X (Twitter) Access Token Secret** | Access Token Secret for that user |

All four are stored in **Settings → AE SEO Writer** (passwords are saved in the database and not shown again after saving).

---

## Step 1: Developer account and app

1. Go to [developer.x.com](https://developer.x.com/) and sign in with the account that owns or will own **@alexevans_io**.
2. If you don’t have a **Developer** account yet, apply for one (e.g. “Hobbyist” / “Making a bot for my own account” is usually enough for posting).
3. In the [Developer Portal](https://developer.x.com/en/portal/dashboard), create a **Project** (if needed) and an **App**.
4. Open your **App** and go to **Keys and tokens**.

---

## Step 2: Consumer keys (API Key + Secret)

1. Under **Consumer Keys**, click **Generate** (or **Regenerate** if you already have keys).
2. Copy and store:
   - **API Key** → paste into **X (Twitter) API Key** in WordPress.
   - **API Secret Key** → paste into **X (Twitter) API Secret** in WordPress.

Keep these secret; they identify your app.

---

## Step 3: User Access Token and Secret (for @alexevans_io)

To post **as** @alexevans_io, the app must have a user token for that account.

1. In the same app, under **Authentication**, set **User authentication** to **Set up** (or **Edit**).
2. Choose **OAuth 1.0a**, enable **Read and write** (and **Read** if required), set **Callback URL** to something like `https://alexevans.io/blog/` or a placeholder (the plugin doesn’t use the callback for server-side posting).
3. Save.
4. Go to **Keys and tokens** again.
5. Under **Access Token and Secret**, click **Generate** (for the user that is @alexevans_io).
6. Copy and store:
   - **Access Token** → paste into **X (Twitter) Access Token** in WordPress.
   - **Access Token Secret** → paste into **X (Twitter) Access Token Secret** in WordPress.

---

## Step 4: App permissions

In the app’s **Settings** (or **User authentication**):

- **App permissions** must include **Read and write** so the app can create tweets.

---

## Summary

| You provide | Plugin uses it for |
|-------------|--------------------|
| API Key (Consumer Key) | OAuth 1.0a signature |
| API Secret (Consumer Secret) | OAuth 1.0a signature |
| Access Token | Post as @alexevans_io |
| Access Token Secret | OAuth 1.0a signature |

After saving these in **Settings → AE SEO Writer**, use **Post to X** on any run detail page to post one of the 4 tweets (with the blog link), or **Schedule 4 daily posts** to auto-post once per day for 4 days.

---

## Troubleshooting

- **“X API credentials not configured”**: All four fields must be filled in Settings.
- **403 / “Could not authenticate you”**: Check that the app has **Read and write** and that the Access Token was generated **after** enabling user authentication (OAuth 1.0a).
- **Rate limits**: The free tier allows a limited number of tweets per month; the plugin posts one tweet per button click or one per day when scheduled.
