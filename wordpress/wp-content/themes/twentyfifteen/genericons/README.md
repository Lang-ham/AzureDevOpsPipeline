## Genericons

Genericons are vector icons embedded in a webfont designed to be clean and simple keeping with a generic aesthetic.

Use genericons for instant HiDPI, to change icon colors on the fly, or even with CSS effects such as drop-shadows or gradients!


### Usage

To use it, place the `font` folder in your stylesheet directory and enqueue the genericons.css file. Now you can create an icon like this:

```
.my-icon:before {
	content: '\f101';
	font: normal 16px/1 'Genericons';
	display: inline-block;
	-webkit-font-smoothing: antialiased;
	-moz-osx-font-smoothing: grayscale;
}
```

This will output a comment icon before every element with the class "my-icon". The `content: '\f101';` part of this CSS is easily copied from the helper tool at http://genericons.com/, or `example.html` in the `font` directory.

You can also use the bundled example.css if you'd rather insert the icons using HTML tags.


### Notes

**Photoshop mockups**

The `Genericons.ttf` file found in the `font` directory can be placed in your system fonts folder and used Photoshop or other graphics apps if you like.

If you're using Genericons in your Photoshop mockups, please remember to delete the old version of the font from Font Book, and grab the new one from the zip file. This also affects using it in your webdesigns: if you have an old version of the font installed locally, that's the font that'll be used in your website as well, so if you're missing icons, check for old versions of the font on your system.

**Pixel grid**

Genericons has been designed for a 16x16px grid. That means it'll look sharp at font-size: 16px exactly. It'll also be crisp at multiples thereof, such as 32px or 64px. It'll look reasonably crisp at in-between font sizes such as 24px or 48px, but not quite as crisp as 16 or 32. Please don't set the font-size to 17px, though, that'll just look terrible blurry.

**Antialiasing**

If you keep intact the `-webkit-font-smoothing: antialiased;` and `-moz-osx-font-smoothing: grayscale;` CSS properties. That'll make the icons look their best possible, in Firefox and WebKit based browsers.

**optimizeLegibility**

Note: On Android browsers with version 4.2, 4.3, and probably later, Genericons will simply not show up if you're using the CSS property "text-rendering" set to "optimizeLegibility.

**Updates**

We don't often update icons, but do very carefully when we get good feedback suggesting improvements. Please be mindful if you upgrade, and check that the updated icons behave as you intended.


### Changelog

**3.2**

A number of new icons and a couple of quick updates. 

* New: Activity
* New: HTML anchor
* New: Bug
* New: Download
* New: Handset
* New: Microphone
* New: Minus
* New: Plus
* New: Move
* New: Rating stars, empty, half, full
* New: Shuffle
* New: video camera
* New: Spotify
* New: Twitch
* Update: Fixed geometry in Edit icon
* Update: Updated Foursquare ico