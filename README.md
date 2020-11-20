# binary-phantomjs

### Deprecated ###
Use https://github.com/jakoch/phantomjs-installer instead

[![Latest Stable Version](https://poser.pugx.org/lanfest/binary-phantomjs/v/stable)](https://packagist.org/packages/lanfest/binary-phantomjs)
[![Total Downloads](https://poser.pugx.org/lanfest/binary-phantomjs/downloads)](https://packagist.org/packages/lanfest/binary-phantomjs)
[![Daily Downloads](https://poser.pugx.org/lanfest/binary-phantomjs/d/daily)](https://packagist.org/packages/lanfest/binary-phantomjs)
[![License](https://poser.pugx.org/lanfest/binary-phantomjs/license)](https://packagist.org/packages/lanfest/binary-phantomjs)

A Composer package which installs the PhantomJS binary (Linux, Windows, Mac) into `/bin` of your project.

The downloaded binary version will chosebn based on che package version. This means that the following configuration will download the 2.1.1 release of PhantomJs:

```json
{
    "require": {
        "lanfest/binary-phantomjs": "2.1.1"
    }
}
```

