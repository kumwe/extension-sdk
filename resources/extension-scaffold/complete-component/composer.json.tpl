{
  "name": "@@EXTENSION_IDENTIFIER@@",
  "description": "@@LABEL_JSON@@ component for Kumwe 2.",
  "type": "kumwe-extension",
  "license": "proprietary",
  "require": {
    "php": "^8.5",
    "doctrine/dbal": "^4.3",
    "kumwe/extension-sdk": "0.3.1",
    "laminas/laminas-diactoros": "^3.6",
    "psr/http-message": "^2.0",
    "psr/http-server-handler": "^1.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.5",
    "kumwe/computation": "0.2.1"
  },
  "autoload": {
    "psr-4": {
      "@@PHP_NAMESPACE_JSON@@\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "@@PHP_NAMESPACE_JSON@@\\Tests\\": "tests/"
    }
  },
  "scripts": {
    "test": "phpunit"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/access-control.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/business-policy.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/extension-sdk.git"
    }
  ],
  "minimum-stability": "stable",
  "prefer-stable": true
}
