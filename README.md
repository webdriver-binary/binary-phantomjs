binary-phantomjs
===================

A Composer package which installs the PhantomJS binary (Linux, Windows, Mac) into `/bin` of your project.

The downloaded binary version will chosebn based on che package version. This means that the following configuration will download the 2.1.1 release of PhantomJs:

```json
{
    "require": {
        "vaimo/binary-phantomjs": "2.1.1"
    }
}
```

