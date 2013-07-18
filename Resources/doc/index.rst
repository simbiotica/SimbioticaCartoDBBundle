SimbioticaCartoDBBundle
=======================

CartoDB SQL API integration for Symfony2

Before you start
----------------

Documentation pages are not rendered correctly on github, and some snippets may not
be visible on the github page.

Prerequisites
-------------

This version of the bundle was developed using Symfony 2.2 and PHP 5.4. 
It *should* work on Symfony 2.x and PHP 5.3, but was not tested or developed with
backwards compatibility in mind. If you find something that doesn't work, feel
free to submit a PR. I'll try and keep compatibility with Symfony 2.1 and PHP 5.3
based on feedback.


Installation
------------

Add the bundle to your composer.json

.. code-block:: javascript
  {
      "require": {
          "simbiotica/cartodb-bundle": "dev-master",
      }
  }


And tell composer to download it by running the command:

.. code-block:: sh

  $ php composer.phar update simbiotica/cartodb-bundle


Next, enable it in you app/AppKernel.php

.. code-block:: php
  <?php
  // app/AppKernel.php
  
  public function registerBundles()
  {
      $bundles = array(
          // ...
          new Simbiotica\CartoDBBundle\SimbioticaCartoDBBundle(),
      );
  }

