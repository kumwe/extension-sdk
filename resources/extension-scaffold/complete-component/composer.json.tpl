{
  "name": "@@EXTENSION_IDENTIFIER@@",
  "description": "@@LABEL_JSON@@ component for Kumwe 2.",
  "type": "kumwe-extension",
  "license": "proprietary",
  "require": {
    "php": "^8.5",
    "doctrine/dbal": "^4.3",
    "kumwe/extension-sdk": "dev-agent/canonical-package-boundary-v2",
    "laminas/laminas-diactoros": "^3.6",
    "psr/http-message": "^2.0",
    "psr/http-server-handler": "^1.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.5",
    "kumwe/computation": "dev-codex/native-adapter-candidate"
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
      "url": "https://github.com/kumwe/administrator-contract.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/automation.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/business-definition.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/business-policy.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/business-surface-contract.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/canonical-json.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/computation.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/idempotency.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/integration.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/portal-contract.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/record-model.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/record-query.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/record-values.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/reporting.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/sequence.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/kumwe/extension-sdk.git"
    }
  ],
  "minimum-stability": "dev",
  "prefer-stable": true
}
