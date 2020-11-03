## THEMES

### Creating a theme

1. First create the theme directory and content by copying the default theme:

```
cp resources/themes/default resources/themes/mytheme
```

2. Compile resources. This will also make sure to copy static files (e.g. images)
   to `public/themes/`:

```
npm run prod
```

3. Configure the app to use your new theme (in .env file):

```
APP_THEME=mytheme
```

### Styles

The main theme directory should include following files:

- "theme.json": Theme metadata, e.g. menu definition.
- "app.scss": The app styles.
- "document.scss": Documents styles.
- "images/logo_header.png": An image that is not controlled by the theme (yet).
- "images/logo_footer.png": An image that is not controlled by the theme (yet).
- "images/favicon.ico": An image that is not controlled by the theme (yet).

Note: Applying some styles to `<body>` or other elements outside of the template
content can be done using `.page-<page>` class that is always added to the `<body>`.

### Menu definition

The menu items are defined using "menu" property in `theme.json` file.
It should be an array of object. Here are all available properties for such an object.

- "title" (string): The displayed label for the menu item. Required.
- "location" (string): The page location. Can be a full URL (for external pages)
  or relative path starting with a slash for internal locations. Required.
- "page" (string): The name of the page. Required for internal pages.
  This is the first element of the page template file which should exist
  in `resources/themes/<theme>/pages/` directory. The template file name should be
  `<page>.blade.php`.
- "footer" (bool): Whether the menu should appear only in the footer menu.

Note that menu definition should not include special pages like "Signup", "Contact" or "Login".

### Page templates

Page content templates placed in `resources/themes/<theme>/pages/` directory are
Blade templates. Some notes about that:

- the content will be placed inside the page layout so you should not use <html> nor <body>
  nor even a wrapper <div>.
- for internal links use `href="/<page_name>"`. Such links will be handled by
  Vue router (without page reload).
- for images or other resource files use `@theme_asset(images/file.jpg)`.

See also: https://laravel.com/docs/6.x/blade
