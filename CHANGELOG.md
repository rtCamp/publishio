# Changelog

## [0.3.0](https://github.com/rtCamp/publishwithai/compare/v0.2.0...v0.3.0) (2026-05-28)


### Features

* add taxonomy/Yoast abilities, skill file, and uninstall table cleanup ([4da5e39](https://github.com/rtCamp/publishwithai/commit/4da5e39612b879ad5c84d8fd3709c0b33aaa8999))
* add taxonomy/Yoast MCP abilities, skill file, and uninstall cleanup ([479b9e5](https://github.com/rtCamp/publishwithai/commit/479b9e5523764a25c173372f452d636ba7dcc250))
* **ci:** implement find-free-port action to dynamically allocate wp-env port ([fc39246](https://github.com/rtCamp/publishwithai/commit/fc392464fdf5a46d9c9431457bd4f184455cd915))
* **mcp:** expose plugin on dedicated /wp-json/mcp/rt-publish-with-ai server ([9fda273](https://github.com/rtCamp/publishwithai/commit/9fda2738dbbdf1ae4b31285b115a931a4b59c02f))
* **mcp:** expose plugin on dedicated /wp-json/mcp/rt-publish-with-ai server ([2b88c8d](https://github.com/rtCamp/publishwithai/commit/2b88c8d9685e2ae76fd75171a1f9fb08bf186ba9))
* **oauth:** add Dynamic_Client_Store for persisting DCR clients ([e61d656](https://github.com/rtCamp/publishwithai/commit/e61d6565d20e3d702bcbe6e8073216f46ac98aba))
* **oauth:** add RFC 7591 dynamic client registration endpoint ([9f9401e](https://github.com/rtCamp/publishwithai/commit/9f9401ea5a97ae8ec5597a4adffba7dc8d3d9175))
* **oauth:** advertise registration_endpoint in authorization server metadata ([cd38843](https://github.com/rtCamp/publishwithai/commit/cd38843736b7739d5fd5a1f02f15b7f348d15aca))
* **oauth:** Authorization Code Flow with PKCE + Dynamic Client Registration ([849b30f](https://github.com/rtCamp/publishwithai/commit/849b30f2cedd82e5284d0627cc3f3f5e60d38b18))
* **oauth:** extend Client_Registry to support dynamically registered clients ([2c66f6c](https://github.com/rtCamp/publishwithai/commit/2c66f6c491f78b9a6cf2d66a5ccc941d3ade6166))
* **oauth:** skip client_secret validation for public clients in token endpoint ([afd5dd7](https://github.com/rtCamp/publishwithai/commit/afd5dd703ff82ac4830603856bc337705466c65f))
* **screenshot:** add Settings page with screenshot provider configuration ([95b8cad](https://github.com/rtCamp/publishwithai/commit/95b8cadb4ab9b8fff802e88e3a5260673dc4aa41))
* **screenshot:** add token system, preview endpoint, providers, and MCP ability ([18c53ab](https://github.com/rtCamp/publishwithai/commit/18c53ab125cd7ca091df02bbde7e32361fd40f24))
* **screenshot:** MCP screenshot-post tool with ephemeral preview URLs ([8a39446](https://github.com/rtCamp/publishwithai/commit/8a39446635599707d88b8c05177abc2b548d19e7))
* **screenshot:** serve preview screenshots as public URLs via ephemeral image endpoint ([619c51d](https://github.com/rtCamp/publishwithai/commit/619c51d88531351b4f96f1482eabac34e2b85427))
* support per-client revocation ([d9d722e](https://github.com/rtCamp/publishwithai/commit/d9d722e2716dd68cd328cec69179baa8b3bc4f5b))
* support per-client revocation ([ce14c62](https://github.com/rtCamp/publishwithai/commit/ce14c628c558f8102ac9790b7e749edbce8cddc1))


### Bug Fixes

* bad namespace ([1702859](https://github.com/rtCamp/publishwithai/commit/17028593bdd3444947c53f749d09b58f760a086a))
* **ci:** isolate wp-env per job and resolve dynamic port for e2e ([e1ecfa6](https://github.com/rtCamp/publishwithai/commit/e1ecfa6413acdcdf1552a517ee627016dc66a14c))
* **ci:** isolate wp-env per job and resolve dynamic port for e2e ([76fcef0](https://github.com/rtCamp/publishwithai/commit/76fcef02b81a391e36d9b70f201eadc7bf87709e))
* **ci:** update E2E test port forwarding to use host gateway IP ([0216446](https://github.com/rtCamp/publishwithai/commit/0216446269298be067a13b463264418bf9678d53))
* **oauth:** address code review findings ([cf87bed](https://github.com/rtCamp/publishwithai/commit/cf87bed5ee3179f8d5eab5273a153298fecdfb95))
* **oauth:** correct Token_Store namespace in Bearer_Token_Auth and Profile_Section ([d206038](https://github.com/rtCamp/publishwithai/commit/d206038f3a58e7a2ef643670645eb7695da1d301))
* **oauth:** correct wp_hash calls and add insert error logging in Token_Store ([34ea4a2](https://github.com/rtCamp/publishwithai/commit/34ea4a2a8c2472d0b792959059eb401a09253851))


### Miscellaneous Chores

* **deps:** bump to Node 24 and regenerate lockfile ([61b1c5b](https://github.com/rtCamp/publishwithai/commit/61b1c5bcf75026e749cdc0a2979a6686997cb360))
* **deps:** update package-lock.json ([78101a1](https://github.com/rtCamp/publishwithai/commit/78101a1c0f9a4680be9c4315c6512a238df630ad))
* **main:** release 0.2.0 ([e6e8032](https://github.com/rtCamp/publishwithai/commit/e6e80321549d410ef0f2038e1c26f394387230e4))
* **main:** release 0.2.0 ([fa83913](https://github.com/rtCamp/publishwithai/commit/fa839139bb148006e9b550988c8ba84add07b438))
* update package-lock.json ([798a897](https://github.com/rtCamp/publishwithai/commit/798a897a2c7c7f90063090eb0fbec6c7d33e0050))

## [0.2.0](https://github.com/rtCamp/publishwithai/compare/v0.1.0...v0.2.0) (2026-05-28)


### Features

* add taxonomy/Yoast abilities, skill file, and uninstall table cleanup ([4da5e39](https://github.com/rtCamp/publishwithai/commit/4da5e39612b879ad5c84d8fd3709c0b33aaa8999))
* add taxonomy/Yoast MCP abilities, skill file, and uninstall cleanup ([479b9e5](https://github.com/rtCamp/publishwithai/commit/479b9e5523764a25c173372f452d636ba7dcc250))
* **ci:** implement find-free-port action to dynamically allocate wp-env port ([fc39246](https://github.com/rtCamp/publishwithai/commit/fc392464fdf5a46d9c9431457bd4f184455cd915))
* **mcp:** expose plugin on dedicated /wp-json/mcp/rt-publish-with-ai server ([9fda273](https://github.com/rtCamp/publishwithai/commit/9fda2738dbbdf1ae4b31285b115a931a4b59c02f))
* **mcp:** expose plugin on dedicated /wp-json/mcp/rt-publish-with-ai server ([2b88c8d](https://github.com/rtCamp/publishwithai/commit/2b88c8d9685e2ae76fd75171a1f9fb08bf186ba9))
* **oauth:** add Dynamic_Client_Store for persisting DCR clients ([e61d656](https://github.com/rtCamp/publishwithai/commit/e61d6565d20e3d702bcbe6e8073216f46ac98aba))
* **oauth:** add RFC 7591 dynamic client registration endpoint ([9f9401e](https://github.com/rtCamp/publishwithai/commit/9f9401ea5a97ae8ec5597a4adffba7dc8d3d9175))
* **oauth:** advertise registration_endpoint in authorization server metadata ([cd38843](https://github.com/rtCamp/publishwithai/commit/cd38843736b7739d5fd5a1f02f15b7f348d15aca))
* **oauth:** Authorization Code Flow with PKCE + Dynamic Client Registration ([849b30f](https://github.com/rtCamp/publishwithai/commit/849b30f2cedd82e5284d0627cc3f3f5e60d38b18))
* **oauth:** extend Client_Registry to support dynamically registered clients ([2c66f6c](https://github.com/rtCamp/publishwithai/commit/2c66f6c491f78b9a6cf2d66a5ccc941d3ade6166))
* **oauth:** skip client_secret validation for public clients in token endpoint ([afd5dd7](https://github.com/rtCamp/publishwithai/commit/afd5dd703ff82ac4830603856bc337705466c65f))
* **screenshot:** add Settings page with screenshot provider configuration ([95b8cad](https://github.com/rtCamp/publishwithai/commit/95b8cadb4ab9b8fff802e88e3a5260673dc4aa41))
* **screenshot:** add token system, preview endpoint, providers, and MCP ability ([18c53ab](https://github.com/rtCamp/publishwithai/commit/18c53ab125cd7ca091df02bbde7e32361fd40f24))
* **screenshot:** MCP screenshot-post tool with ephemeral preview URLs ([8a39446](https://github.com/rtCamp/publishwithai/commit/8a39446635599707d88b8c05177abc2b548d19e7))
* **screenshot:** serve preview screenshots as public URLs via ephemeral image endpoint ([619c51d](https://github.com/rtCamp/publishwithai/commit/619c51d88531351b4f96f1482eabac34e2b85427))
* support per-client revocation ([d9d722e](https://github.com/rtCamp/publishwithai/commit/d9d722e2716dd68cd328cec69179baa8b3bc4f5b))
* support per-client revocation ([ce14c62](https://github.com/rtCamp/publishwithai/commit/ce14c628c558f8102ac9790b7e749edbce8cddc1))


### Bug Fixes

* bad namespace ([1702859](https://github.com/rtCamp/publishwithai/commit/17028593bdd3444947c53f749d09b58f760a086a))
* **ci:** isolate wp-env per job and resolve dynamic port for e2e ([e1ecfa6](https://github.com/rtCamp/publishwithai/commit/e1ecfa6413acdcdf1552a517ee627016dc66a14c))
* **ci:** isolate wp-env per job and resolve dynamic port for e2e ([76fcef0](https://github.com/rtCamp/publishwithai/commit/76fcef02b81a391e36d9b70f201eadc7bf87709e))
* **ci:** update E2E test port forwarding to use host gateway IP ([0216446](https://github.com/rtCamp/publishwithai/commit/0216446269298be067a13b463264418bf9678d53))
* **oauth:** address code review findings ([cf87bed](https://github.com/rtCamp/publishwithai/commit/cf87bed5ee3179f8d5eab5273a153298fecdfb95))
* **oauth:** correct Token_Store namespace in Bearer_Token_Auth and Profile_Section ([d206038](https://github.com/rtCamp/publishwithai/commit/d206038f3a58e7a2ef643670645eb7695da1d301))
* **oauth:** correct wp_hash calls and add insert error logging in Token_Store ([34ea4a2](https://github.com/rtCamp/publishwithai/commit/34ea4a2a8c2472d0b792959059eb401a09253851))


### Miscellaneous Chores

* **deps:** bump to Node 24 and regenerate lockfile ([61b1c5b](https://github.com/rtCamp/publishwithai/commit/61b1c5bcf75026e749cdc0a2979a6686997cb360))
* **deps:** update package-lock.json ([78101a1](https://github.com/rtCamp/publishwithai/commit/78101a1c0f9a4680be9c4315c6512a238df630ad))
* update package-lock.json ([798a897](https://github.com/rtCamp/publishwithai/commit/798a897a2c7c7f90063090eb0fbec6c7d33e0050))

## [0.0.1] - 2026-05-21

- Initial release.
