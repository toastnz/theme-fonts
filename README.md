### Installation
------------

The easiest way is to use [composer](https://getcomposer.org/):

    composer require toastnz/theme-fonts

Run `dev/build` afterwards.

### Configuration
-------------

Add the following to your `config.yml` (optional) to generate default fonts on dev/build
Fonts with a value will be locked and not editable in the CMS
Fonts with null value will be editable in the CMS

```yaml
Toast\ThemeColours\Models:
  default_colours:
    - body: null
    - headings: null
    - comic-sans: 'comic-sans'
```

### Usage
-------------
### Colour functions 
```getFontFamilyClassName()``` returns `f-` + `getFontCustomID()` so the css class is unique. `f-` is there to represent `font` and to ensure the class does not start with a number.