# Plugin Update Endpoints

A plugin for exposing the update urls for plugins. Used in combination with the plugin updater workflow on GitHub

## How to use

When installing the plugin to a WordPress install, you are able to fetch the download source files by calling this endpoint:

`https://example.com/wp-json/plugin-update-endpoints/v1/download/`

The plugin uses the following parameters:

- **auth**: Auth code for using the endpoint. This should match a constant in your wp-config.php with the name of `DC_PUE_AUTH_CODE`.
- **plugin**: The plugin slug. This is the plugin you want to download.

A complete url would look like this:

`https://example.com/wp-json/plugin-update-endpoints/v1/download/?auth=123&plugin=contact-form-7`

## Responses
### Success
- **Plugin update is available**: Redirected with a 301 to the source zip.
- **No update**: 204, No content.
### Error
- **No auth**: 401, Missing Authentication code.
- **Missing auth**: 401, Authentication code is not defined on the server.
- **Wrong auth**: 401, Wrong authentication code.
- **No plugin parameter**: 401, Missing plugin parameter with plugin slug.
- **Plugin doesn't exist**: 404, Plugin does not exist on the WordPress install.
