## What changed

<!-- One or two sentences. -->

## Why

<!-- Link the issue or explain the user problem. -->

## How it was tested

- [ ] `php -l` on every modified PHP file
- [ ] `node --check` on modified JavaScript
- [ ] Manual check of `/sismos`, `/sismos/historico` and `/sismos/detalle/{id}`
- [ ] `/api/health` returns the expected version
- [ ] Radius filter works with and without geolocation permission
- [ ] No secrets, personal data or production domains were added
- [ ] `python3 tools/check-git.py` passes; generated packages and runtime files stay ignored
- [ ] New modules and retired paths are included together; views only render prepared data

## Screenshots / API output

<!-- Optional, but very helpful for UI or API changes. -->
