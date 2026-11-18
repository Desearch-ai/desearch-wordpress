# Desearch for WordPress

Version 0.1.0 adds an administrator-only Tools > Desearch page. It saves a per-site API key and uses the released hosted MCP `search` tool for titled web links.

**Draft, ready for independent review.** After the production service fix in T-1181 on 2026-11-18, the unchanged plugin returned 10 titled absolute links for `WordPress plugin developer handbook`, including the official Plugin Handbook. Invalid-key rejection passed. Mariam's independent exact-head QA, Iza's store-fit review and the T-1128 handover acknowledgment remain pending. No submission, merge or publication is authorized.

## Frozen contract

- Private Desearch-ai repository; base `main` at `5d10cb13b28b72360f9356f3ddd04e3a2c488447`; feature branch `oto/t-1124-wordpress-plugin`.
- WordPress.org target, GPL-2.0-or-later per Mirian's provisioning handover. GPLv2 text is included; plugin headers grant the later-version option. Submission belongs to Iza in T-1128; account/contributor identity is supplied there.
- Tested runtime: WordPress 7.1.4 / PHP 8.3.36 and WordPress 6.6.2 / PHP 7.4.33, with MariaDB 11.4. The declared floor is WordPress 6.6 / PHP 7.4; 6.6.0 itself was not tested. A disposable two-site network verified isolated keys and network uninstall cleanup on 6.6.2 / PHP 7.4.33.
- `https://mcp.desearch.ai/mcp`, stateless JSON Streamable HTTP, protocol header `2025-03-26`, `Authorization: Bearer` from user settings, `tools/call` named `search` with `{query}`. Reference: `Desearch-ai/mcp-desearch` at `b1df2a7f985a1d0d3a2b666e21b7f77d9234d3e9`, `server.ts` output schema and `http.ts` JSON transport. This does not assert the deployed commit.
- No server, dependencies, telemetry, public endpoint or theme changes. Explicit administrator searches only; activation/settings save make no remote request. Fixed errors suppress upstream detail. Database administrators and privileged HTTP hooks can access the key. See packaged readme for service/privacy disclosures.

Official sources checked 2026-11-18 Asia/Tbilisi: [headers](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/), [directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), [readme format](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/), [MCP source](https://github.com/Desearch-ai/mcp-desearch), [terms](https://desearch.ai/terms), [privacy](https://desearch.ai/privacy).

## Build and QA

`python3 scripts/package.py` creates a deterministic `dist/desearch-0.1.0.zip` containing only the plugin directory, and prints SHA-256. No release is created.

For a disposable Docker site, run `python3 scripts/start-test.py`. It creates only task-named containers/network/volume and binds HTTP to `127.0.0.1:18824`. It refuses existing task containers. Credentials are randomly generated and not printed. Then:

```sh
python3 scripts/package.py
python3 scripts/wp-test.py plugin install /work/dist/desearch-0.1.0.zip --activate
python3 scripts/php-test.py smoke.php
python3 scripts/wp-test.py plugin install plugin-check --activate
python3 scripts/wp-test.py plugin check desearch --format=json
```

Live verification (reviewers substitute their own MC identity):

```sh
/Users/cosmic/bin/mc secret run DESEARCH_API_KEY Oto -- 'test -n "$DESEARCH_API_KEY" && python3 scripts/php-test.py live.php'
```

The live test sends one invalid-key search and one real search, requires at least one titled link, prints only safe status and result titles/URLs, and removes the stored key in `finally`. Both tested runtimes passed with 10 links. The plugin has no retry/fallback.

The local suite uses the installed ZIP and covers capabilities, nonce rejection before effects, autoload, key preservation/removal, query bounds, header injection, escaping, unsafe/relative links, JSON/text payloads, network/auth/rate errors and malformed responses. PHP lint: `docker exec t1124-wp sh -c 'find /var/www/html/wp-content/plugins/desearch -name "*.php" -exec php -l {} \;'`.

Browser QA covered missing-key search, invalid-key save/search, a blank password field after save, removal, and a successful real search with 10 titled links. Never include a real key in screenshots. Plugin Check reported no errors on WordPress 7.1.4.

To repeat the minimum-runtime checks, first remove only the previous disposable task containers, volume and network, then run `DESEARCH_TEST_RUNTIME=minimum python3 scripts/start-test.py` and repeat the ZIP installation, smoke and live commands above. The helper pins WordPress 6.6.2 and uses the PHP 7.4 container. `php-test.py` loads the admin APIs and sets local request variables so assertions run with the site's actual PHP; WP-CLI itself uses its own PHP 8.3 image. A preliminary bare `wp-load.php` harness failed because it omitted `submit_button`; the corrected admin harness passed the full suite.

On that disposable site only, test network cleanup with:

```sh
python3 scripts/wp-test.py core multisite-convert --title='Desearch QA Network'
python3 scripts/wp-test.py plugin activate desearch --network
python3 scripts/php-test.py multisite.php
```

The network test invokes the packaged uninstall entry point with WordPress's uninstall constant, checks two sites' keys are removed, and preserves unrelated options. It does not test networks larger than 100 sites or network browser routing. Remove only the task's own disposable Docker resources when done. Deactivation keeps the key; uninstall removes it.
