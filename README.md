# binary-phantomjs

[![Latest Stable Version](https://poser.pugx.org/vaimo/binary-phantomjs/v/stable)](https://packagist.org/packages/vaimo/binary-phantomjs)
[![Total Downloads](https://poser.pugx.org/vaimo/binary-phantomjs/downloads)](https://packagist.org/packages/vaimo/binary-phantomjs)
[![Daily Downloads](https://poser.pugx.org/vaimo/binary-phantomjs/d/daily)](https://packagist.org/packages/vaimo/binary-phantomjs)
[![License](https://poser.pugx.org/vaimo/binary-phantomjs/license)](https://packagist.org/packages/vaimo/binary-phantomjs)

A Composer package which installs the PhantomJS binary (Linux, Windows, Mac) into `/bin` of your project.

The downloaded binary version will chosebn based on che package version. This means that the following configuration will download the 2.1.1 release of PhantomJs:

```json
{
    "require": {
        "vaimo/binary-phantomjs": "2.1.1"
    }
}
```

