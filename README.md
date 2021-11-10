# Plugin Update Endpoints

A plugin for exposing the update urls for plugins. Used in combination with the plugin updater workflow on GitHub

## How to use

When installing the plugin to a WordPress install, you are able to fetch the download source files by calling this endpoint:

`https://example.com/wp-json/plugin-update-endpoints/v1/download/`

The plugin uses the following parameters:

-   **auth**: Auth code for using the endpoint. This should match a constant in your wp-config.php with the name of `DC_PUE_AUTH_CODE`.
-   **plugin**: The plugin slug. This is the plugin you want to download.

A complete url would look like this:

`https://example.com/wp-json/plugin-update-endpoints/v1/download/?auth=123&plugin=contact-form-7`
