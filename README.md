# binary-phantomjs

### Deprecated ###
Use https://github.com/jakoch/phantomjs-installer instead

[![Latest Stable Version](https://poser.pugx.org/webdriver-binary/binary-phantomjs/v/stable)](https://packagist.org/packages/webdriver-binary/binary-phantomjs)
[![Total Downloads](https://poser.pugx.org/webdriver-binary/binary-phantomjs/downloads)](https://packagist.org/packages/webdriver-binary/binary-phantomjs)
[![Daily Downloads](https://poser.pugx.org/webdriver-binary/binary-phantomjs/d/daily)](https://packagist.org/packages/webdriver-binary/binary-phantomjs)
[![License](https://poser.pugx.org/webdriver-binary/binary-phantomjs/license)](https://packagist.org/packages/webdriver-binary/binary-phantomjs)

A Composer package which installs the PhantomJS binary (Linux, Windows, Mac) into `/bin` of your project.

The downloaded binary version will be picked based on che package version. This means that the following configuration will download the 2.1.1 release of PhantomJs:

```json
{
    "require": {
        "webdriver-binary/binary-phantomjs": "2.1.1"
    }
}
```

