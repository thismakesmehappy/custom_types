# Create Custom Types

1. Clone the repository into a subfolder in your theme
 - `git clone git@github.com/thismakesmehappy/custom_types.git types`
2. Add the following to `functions.php`
 ```
// Define custom types
$types_directory = '/types';
require_once get_template_directory() . "$types_directory/custom_types_methods.php";
create_custom_post_types();
 ```
- If in step 1 you chang `types` for another subdirectory, make sure `$types_directory` matches that value
3. Customize your types by modifying `custom_type_definitions`
 - `type` should never be longer than 20 characters, or the typ won't show up in the UI