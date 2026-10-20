# Security Notes

## Secret exposure response

If a local `.env` file was ever committed or copied into a patch, treat all contained secrets as compromised.

### Immediate actions

1. Remove `.env` from Git tracking:
   `git rm --cached .env`
2. Keep `.env` ignored:
   `.gitignore` already contains:
   - `.env`
   - `.env.*`
   - `!.env.example`
3. Rotate every exposed secret before the next deployment.

### Rotation checklist

- `APP_KEY`
  Generate a new key with `php artisan key:generate --show` and update `.env` outside Git.
  If existing encrypted cookies, sessions, or payloads must remain readable during rollout, copy the old key into `APP_PREVIOUS_KEYS` temporarily.
- `DB_PASSWORD`
  Change the database user's password in the database server or managed DB console, then update `.env` outside Git.
- `OPENWEATHER_API_KEY`
  Revoke the exposed key in the OpenWeather dashboard, create a replacement key, then update `.env` outside Git.

### History cleanup

If `.env` was committed in any past commit, removing it from the current tree is not enough. Purge it from history before sharing the repository further. Example with `git filter-repo`:

```bash
git filter-repo --path .env --invert-paths
```

After a history rewrite, force-push the affected branches and notify anyone who already cloned the repository that old history must be discarded.
