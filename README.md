SimbioticaCartoDBBundle
=======================

CartoDB SQL API integration for Symfony2

WORK IN PROGRESS - NOT FUNCTIONAL
----------------


## Prerequisites

This version of the bundle was developed using Symfony 2.2 and PHP 5.4. 
It *should* work on Symfony 2.x and PHP 5.3, but was not tested or developed with
backwards compatibility in mind. If you find something that doesn't work, feel
free to submit a PR. I'll try and keep compatibility with Symfony 2.1 and PHP 5.3
based on feedback.


## Instalation

Add the bundle to your composer.json
```js
{
    "require": {
        "simbiotica/cartodb-bundle": "dev-master",
    }
}
```

And tell composer to download it by running the command:

``` bash
$ php composer.phar update simbiotica/cartodb-bundle
```

Next, enable it in you app/AppKernel.php

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Simbiotica\CartoDBBundle\SimbioticaCartoDBBundle(),
    );
}
```

Next stop, configuration:

``` yaml
# app/config/config.yml
simbiotica_cartodb:
    key:
    secret:
    subdomain:
    email:
    password:
```

All fields are required.

