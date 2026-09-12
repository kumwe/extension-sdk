---
{
  "schema": "kumwe-package-release-record/v1",
  "artifact_kind": "framework_php",
  "migration_id": "KUMWE-MIG-2026-033",
  "change_set": "KUMWE-CS-2026-033",
  "source": {
    "app": {
      "repository": "https://github.com/kumwe/app",
      "baseline_commit": null,
      "examined_paths": [],
      "old_namespace_roots": [],
      "capability_index_sha256": null
    },
    "semantic_inputs": [
      {
        "owner": "kumwe/extension-sdk",
        "version_or_commit": "e3fa043bbf8e7e368093f2a9c79ac4d09fa1f94c",
        "manifest_or_corpus": "resources/contract/classification.json",
        "sha256": "15b7d5438d48af1df35f985eba131292e7a46184fc8d5f7436b8a17b95df9c7c"
      },
      {
        "owner": "kumwe/extension-sdk",
        "version_or_commit": "e3fa043bbf8e7e368093f2a9c79ac4d09fa1f94c",
        "manifest_or_corpus": "resources/contract/generations.json",
        "sha256": "78b0138c24f267200c3350bbffcedfb836127d3d2db99f9eda77a8d607bd3b30"
      }
    ],
    "examined_dependencies": [
      "Exact published dependency versions are declared in composer.json. The dependency-selection gate checks the source coordinates in resources/source-ci-dependencies.json and resources/source-candidate-dependencies.json against both CI workflows.",
      "docs/migration-map.json and docs/canonical-package-migration.json preserve canonical symbol ownership for Core integration; their baseline identities are historical provenance."
    ]
  },
  "target": {
    "repository": "https://github.com/kumwe/extension-sdk",
    "artifact_identity": "kumwe/extension-sdk",
    "canonical_namespace_or_abi": "Kumwe\\Extension\\"
  },
  "ownership": {
    "responsibility": "Canonical extension author contracts, strict manifests, executable binding ports and host-neutral package authoring/evidence tooling.",
    "non_responsibilities": [
      "Admission, authorization, trust policy, activation and persistence",
      "Host containers, request/transaction scopes, key distribution and tenant configuration",
      "Algorithms and values owned by extracted capability libraries",
      "Native engine provisioning or a PHP algorithm fallback"
    ],
    "allowed_dependency_ceiling": [
      "PHP and its declared extensions",
      "Published canonical capability contracts listed exactly in composer.json",
      "Doctrine DBAL migration port, PSR HTTP contracts and Ramsey UUID formatting",
      "Native Computation only for optional CLI and development tooling"
    ],
    "implementation_owner": "kumwe/extension-sdk",
    "next_consumer": "Extension authors and host implementations, including Kumwe Core, qualified against the exact published SDK and dependency graph.",
    "public_manifests": [
      {
        "path": "resources/public-api/v1.json",
        "sha256": "9bcc011d36a21a1ea328f2c450087b31f808e890e5d4f157acd725f110a22987"
      },
      {
        "path": "resources/public-api/signature-details-v1.json",
        "sha256": "85cf3a36c11a6ad1e123240bd2d8173f701d3c8449d190cccde61a9ecece5850"
      },
      {
        "path": "resources/capabilities/v1.json",
        "sha256": "c8cb869bebbc725c6ba769d2a82bb74a47a150b4a12a124bc8cb35f0c0c77143"
      },
      {
        "path": "resources/service-map/v1.json",
        "sha256": "d782df039a31280c7fe6b4ef43516f18de305b561a2fbb910d23f89c3d50c5d9"
      },
      {
        "path": "resources/contract/classification.json",
        "sha256": "15b7d5438d48af1df35f985eba131292e7a46184fc8d5f7436b8a17b95df9c7c"
      },
      {
        "path": "resources/contract/generations.json",
        "sha256": "78b0138c24f267200c3350bbffcedfb836127d3d2db99f9eda77a8d607bd3b30"
      },
      {
        "path": "resources/PIN.json",
        "sha256": "946bed4cb5b8e8369f34ee064509c96ce91ae15817b6a4ec2aa4ea74c70f07dd"
      }
    ],
    "intentionally_excluded": [
      "Host application source, configuration, admission policy and persistence are owned by the consumer.",
      "No duplicate capability implementation, namespace alias or ownership reassignment.",
      "No self artifact SHA, future tag identity or embedded release attestation."
    ]
  },
  "framework_php": {
    "composer_package": "kumwe/extension-sdk",
    "canonical_namespace": "Kumwe\\Extension\\",
    "public_api_manifest": "resources/public-api/v1.json",
    "capability_manifest": "resources/capabilities/v1.json",
    "service_map": "resources/service-map/v1.json",
    "extracted_symbols": [
      {
        "old_fqcn": "Kumwe\\Extension\\Contract\\NameBasedUuid",
        "new_fqcn": "Kumwe\\Extension\\Contract\\NameBasedUuid",
        "source_path": "src/Contract/NameBasedUuid.php",
        "target_path": "src/Contract/NameBasedUuid.php",
        "kind": "class",
        "public_methods": [
          "v5"
        ],
        "public_properties": [],
        "public_constants": [
          "NAMESPACE_URL"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Manifest\\ExtensionDependency",
        "new_fqcn": "Kumwe\\Extension\\Manifest\\ExtensionDependency",
        "source_path": "src/Manifest/ExtensionDependency.php",
        "target_path": "src/Manifest/ExtensionDependency.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "extension",
          "constraint",
          "isOptional",
          "isSatisfiedBy"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Manifest\\ExtensionIdentifier",
        "new_fqcn": "Kumwe\\Extension\\Manifest\\ExtensionIdentifier",
        "source_path": "src/Manifest/ExtensionIdentifier.php",
        "target_path": "src/Manifest/ExtensionIdentifier.php",
        "kind": "class",
        "public_methods": [
          "fromString",
          "value",
          "equals",
          "__toString"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Manifest\\ExtensionManifest",
        "new_fqcn": "Kumwe\\Extension\\Manifest\\ExtensionManifest",
        "source_path": "src/Manifest/ExtensionManifest.php",
        "target_path": "src/Manifest/ExtensionManifest.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "fromJson",
          "schemaVersion",
          "identifier",
          "type",
          "version",
          "serviceProvider",
          "supports",
          "dependencies",
          "autoload",
          "migrations",
          "configuration",
          "permissions",
          "schemaOneRoutes",
          "schemaOneEvents",
          "assets",
          "contributions",
          "templateCompatibility"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Manifest\\ExtensionType",
        "new_fqcn": "Kumwe\\Extension\\Manifest\\ExtensionType",
        "source_path": "src/Manifest/ExtensionType.php",
        "target_path": "src/Manifest/ExtensionType.php",
        "kind": "enum",
        "public_methods": [
          "cases",
          "from",
          "tryFrom"
        ],
        "public_properties": [
          "name",
          "value"
        ],
        "public_constants": [
          "Plugin",
          "Module",
          "Template",
          "Component",
          "Package",
          "Language"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Manifest\\ManifestContributions",
        "new_fqcn": "Kumwe\\Extension\\Manifest\\ManifestContributions",
        "source_path": "src/Manifest/ManifestContributions.php",
        "target_path": "src/Manifest/ManifestContributions.php",
        "kind": "class",
        "public_methods": [
          "fromManifest",
          "fromSchemaOne",
          "spiVersion",
          "capabilityIdentifiers",
          "administratorWorkspaces",
          "administratorWorkspace",
          "administratorNavigation",
          "administratorNavigationItem",
          "administratorRoutes",
          "administratorRoute",
          "administratorViews",
          "administratorView",
          "portalWorkspaces",
          "portalWorkspace",
          "portalNavigation",
          "portalNavigationItem",
          "portalRoutes",
          "portalRoute",
          "portalTemplates",
          "portalTemplate",
          "fieldPresentations",
          "fieldPresentation",
          "domainListeners",
          "domainListener",
          "eventConsumers",
          "eventConsumer",
          "jobs",
          "job",
          "projections",
          "projection",
          "webhooks",
          "webhook",
          "compositionBlocks",
          "compositionBlock",
          "compositionPatterns",
          "compositionPattern",
          "compositionFieldControls",
          "compositionFieldControl",
          "compositionInspectors",
          "compositionInspector",
          "compositionDesignVocabularies",
          "compositionDesignVocabulary",
          "compositionMigrations",
          "compositionMigration",
          "canonicalCompositionDocuments",
          "canonicalCompositionDocument",
          "compositionHostBindings",
          "compositionHostBinding",
          "executableBindingRequirements",
          "surfaceCounts",
          "declarations",
          "toArray"
        ],
        "public_properties": [
          "owner"
        ],
        "public_constants": [
          "SPI_VERSION",
          "CURRENT_SPI_VERSION",
          "COMPOSITION_SPI_VERSION",
          "CANONICAL_COMPOSITION_SPI_VERSION"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Manifest\\ManifestIdentifierPolicies",
        "new_fqcn": "Kumwe\\Extension\\Manifest\\ManifestIdentifierPolicies",
        "source_path": "src/Manifest/ManifestIdentifierPolicies.php",
        "target_path": "src/Manifest/ManifestIdentifierPolicies.php",
        "kind": "class",
        "public_methods": [
          "forKind"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Manifest\\SemanticVersion",
        "new_fqcn": "Kumwe\\Extension\\Manifest\\SemanticVersion",
        "source_path": "src/Manifest/SemanticVersion.php",
        "target_path": "src/Manifest/SemanticVersion.php",
        "kind": "class",
        "public_methods": [
          "fromString",
          "major",
          "minor",
          "patch",
          "isPreRelease",
          "compare",
          "__toString"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Manifest\\TemplateKisCompatibility",
        "new_fqcn": "Kumwe\\Extension\\Manifest\\TemplateKisCompatibility",
        "source_path": "src/Manifest/TemplateKisCompatibility.php",
        "target_path": "src/Manifest/TemplateKisCompatibility.php",
        "kind": "class",
        "public_methods": [
          "schemaOneKis",
          "fromArray",
          "contract",
          "standard",
          "supportsComponents",
          "supportsTokens"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Manifest\\VersionConstraint",
        "new_fqcn": "Kumwe\\Extension\\Manifest\\VersionConstraint",
        "source_path": "src/Manifest/VersionConstraint.php",
        "target_path": "src/Manifest/VersionConstraint.php",
        "kind": "class",
        "public_methods": [
          "fromString",
          "accepts",
          "__toString"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\ArchiveContentReader",
        "new_fqcn": "Kumwe\\Extension\\Package\\ArchiveContentReader",
        "source_path": "src/Package/ArchiveContentReader.php",
        "target_path": "src/Package/ArchiveContentReader.php",
        "kind": "interface",
        "public_methods": [
          "contents"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\ArchiveEntry",
        "new_fqcn": "Kumwe\\Extension\\Package\\ArchiveEntry",
        "source_path": "src/Package/ArchiveEntry.php",
        "target_path": "src/Package/ArchiveEntry.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "path",
          "type",
          "compressedBytes",
          "uncompressedBytes",
          "encrypted"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\ArchiveEntryType",
        "new_fqcn": "Kumwe\\Extension\\Package\\ArchiveEntryType",
        "source_path": "src/Package/ArchiveEntryType.php",
        "target_path": "src/Package/ArchiveEntryType.php",
        "kind": "enum",
        "public_methods": [
          "cases",
          "from",
          "tryFrom"
        ],
        "public_properties": [
          "name",
          "value"
        ],
        "public_constants": [
          "File",
          "Directory",
          "SymbolicLink",
          "Special"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\ArchivePackage",
        "new_fqcn": "Kumwe\\Extension\\Package\\ArchivePackage",
        "source_path": "src/Package/ArchivePackage.php",
        "target_path": "src/Package/ArchivePackage.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "entries"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\ArchiveReader",
        "new_fqcn": "Kumwe\\Extension\\Package\\ArchiveReader",
        "source_path": "src/Package/ArchiveReader.php",
        "target_path": "src/Package/ArchiveReader.php",
        "kind": "interface",
        "public_methods": [
          "inspect"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\InspectedPackage",
        "new_fqcn": "Kumwe\\Extension\\Package\\InspectedPackage",
        "source_path": "src/Package/InspectedPackage.php",
        "target_path": "src/Package/InspectedPackage.php",
        "kind": "class",
        "public_methods": [
          "inspect",
          "paths",
          "expandedBytes",
          "hasNoSafetyFindings",
          "assertCurrentArchiveIdentity",
          "__serialize",
          "__unserialize"
        ],
        "public_properties": [
          "archive",
          "checksum",
          "entries",
          "manifest",
          "manifestJson",
          "limits",
          "safetyFindings"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\InvalidPackage",
        "new_fqcn": "Kumwe\\Extension\\Package\\InvalidPackage",
        "source_path": "src/Package/InvalidPackage.php",
        "target_path": "src/Package/InvalidPackage.php",
        "kind": "class",
        "public_methods": [
          "__construct"
        ],
        "public_properties": [
          "finding"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageAttestationState",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageAttestationState",
        "source_path": "src/Package/PackageAttestationState.php",
        "target_path": "src/Package/PackageAttestationState.php",
        "kind": "enum",
        "public_methods": [
          "cases",
          "from",
          "tryFrom"
        ],
        "public_properties": [
          "name",
          "value"
        ],
        "public_constants": [
          "NotInspected",
          "Verified",
          "Absent",
          "Invalid"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageBillOfMaterials",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageBillOfMaterials",
        "source_path": "src/Package/PackageBillOfMaterials.php",
        "target_path": "src/Package/PackageBillOfMaterials.php",
        "kind": "class",
        "public_methods": [
          "forPackage",
          "fromJson",
          "fileDigests",
          "reconcile",
          "componentCount",
          "toJson"
        ],
        "public_properties": [
          "document"
        ],
        "public_constants": [
          "PATH",
          "SPEC_VERSION",
          "MAXIMUM_BYTES"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageChecksum",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageChecksum",
        "source_path": "src/Package/PackageChecksum.php",
        "target_path": "src/Package/PackageChecksum.php",
        "kind": "class",
        "public_methods": [
          "sha256",
          "calculate",
          "matches",
          "__toString"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageCodeConformance",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageCodeConformance",
        "source_path": "src/Package/PackageCodeConformance.php",
        "target_path": "src/Package/PackageCodeConformance.php",
        "kind": "class",
        "public_methods": [
          "phpFindings",
          "markerViolations",
          "referenceViolations",
          "isTextPath",
          "isPhpPath"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageEvidenceInspector",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageEvidenceInspector",
        "source_path": "src/Package/PackageEvidenceInspector.php",
        "target_path": "src/Package/PackageEvidenceInspector.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "inspect"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageEvidenceReport",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageEvidenceReport",
        "source_path": "src/Package/PackageEvidenceReport.php",
        "target_path": "src/Package/PackageEvidenceReport.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "toArray",
          "auditMetadata"
        ],
        "public_properties": [
          "scope",
          "sbomState",
          "sbomSha256",
          "sbomComponents",
          "sbom",
          "provenanceState",
          "provenanceSha256",
          "builderReference",
          "provenance",
          "checks",
          "findings"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageEvidenceScope",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageEvidenceScope",
        "source_path": "src/Package/PackageEvidenceScope.php",
        "target_path": "src/Package/PackageEvidenceScope.php",
        "kind": "enum",
        "public_methods": [
          "includesAuthoring",
          "cases",
          "from",
          "tryFrom"
        ],
        "public_properties": [
          "name",
          "value"
        ],
        "public_constants": [
          "Package",
          "Authoring"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageFinding",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageFinding",
        "source_path": "src/Package/PackageFinding.php",
        "target_path": "src/Package/PackageFinding.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "toArray",
          "__toString"
        ],
        "public_properties": [
          "code",
          "message",
          "path"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageLimits",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageLimits",
        "source_path": "src/Package/PackageLimits.php",
        "target_path": "src/Package/PackageLimits.php",
        "kind": "class",
        "public_methods": [
          "__construct"
        ],
        "public_properties": [
          "maximumEntries",
          "maximumEntryBytes",
          "maximumExpandedBytes",
          "maximumCompressedBytes",
          "maximumArchiveBytes",
          "maximumCompressionRatio",
          "maximumManifestBytes",
          "maximumBillOfMaterialsBytes",
          "maximumProvenanceBytes",
          "readChunkBytes"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackagePath",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackagePath",
        "source_path": "src/Package/PackagePath.php",
        "target_path": "src/Package/PackagePath.php",
        "kind": "class",
        "public_methods": [
          "fromString",
          "value",
          "__toString"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageProvenance",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageProvenance",
        "source_path": "src/Package/PackageProvenance.php",
        "target_path": "src/Package/PackageProvenance.php",
        "kind": "class",
        "public_methods": [
          "forPackage",
          "fromJson",
          "reconcile",
          "builderReference",
          "toJson"
        ],
        "public_properties": [
          "statement"
        ],
        "public_constants": [
          "PATH",
          "FORMAT",
          "BUILD_TYPE",
          "BUILDER_NAME",
          "BUILDER_VERSION",
          "MAXIMUM_BYTES"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageSafetyInspector",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageSafetyInspector",
        "source_path": "src/Package/PackageSafetyInspector.php",
        "target_path": "src/Package/PackageSafetyInspector.php",
        "kind": "class",
        "public_methods": [
          "findings"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageSignature",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageSignature",
        "source_path": "src/Package/PackageSignature.php",
        "target_path": "src/Package/PackageSignature.php",
        "kind": "class",
        "public_methods": [
          "ed25519",
          "keyId",
          "algorithm",
          "bytes",
          "asBase64"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageSignatureMessage",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageSignatureMessage",
        "source_path": "src/Package/PackageSignatureMessage.php",
        "target_path": "src/Package/PackageSignatureMessage.php",
        "kind": "class",
        "public_methods": [
          "forChecksum"
        ],
        "public_properties": [],
        "public_constants": [
          "DOMAIN"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PackageSignatureVerifier",
        "new_fqcn": "Kumwe\\Extension\\Package\\PackageSignatureVerifier",
        "source_path": "src/Package/PackageSignatureVerifier.php",
        "target_path": "src/Package/PackageSignatureVerifier.php",
        "kind": "interface",
        "public_methods": [
          "verify"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\PublicKeyPackageSignatureVerifier",
        "new_fqcn": "Kumwe\\Extension\\Package\\PublicKeyPackageSignatureVerifier",
        "source_path": "src/Package/PublicKeyPackageSignatureVerifier.php",
        "target_path": "src/Package/PublicKeyPackageSignatureVerifier.php",
        "kind": "interface",
        "public_methods": [
          "verify"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\SodiumEd25519Verifier",
        "new_fqcn": "Kumwe\\Extension\\Package\\SodiumEd25519Verifier",
        "source_path": "src/Package/SodiumEd25519Verifier.php",
        "target_path": "src/Package/SodiumEd25519Verifier.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "verify"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\SodiumPublicKeyPackageSignatureVerifier",
        "new_fqcn": "Kumwe\\Extension\\Package\\SodiumPublicKeyPackageSignatureVerifier",
        "source_path": "src/Package/SodiumPublicKeyPackageSignatureVerifier.php",
        "target_path": "src/Package/SodiumPublicKeyPackageSignatureVerifier.php",
        "kind": "class",
        "public_methods": [
          "verify"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\ZipArchiveContentReader",
        "new_fqcn": "Kumwe\\Extension\\Package\\ZipArchiveContentReader",
        "source_path": "src/Package/ZipArchiveContentReader.php",
        "target_path": "src/Package/ZipArchiveContentReader.php",
        "kind": "class",
        "public_methods": [
          "contents"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Package\\ZipArchiveReader",
        "new_fqcn": "Kumwe\\Extension\\Package\\ZipArchiveReader",
        "source_path": "src/Package/ZipArchiveReader.php",
        "target_path": "src/Package/ZipArchiveReader.php",
        "kind": "class",
        "public_methods": [
          "inspect"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Application\\Automation\\JobHandler",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Application\\Automation\\JobHandler",
        "source_path": "src/Spi/Application/Automation/JobHandler.php",
        "target_path": "src/Spi/Application/Automation/JobHandler.php",
        "kind": "interface",
        "public_methods": [
          "handle"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Application\\ExecutionContext",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Application\\ExecutionContext",
        "source_path": "src/Spi/Application/ExecutionContext.php",
        "target_path": "src/Spi/Application/ExecutionContext.php",
        "kind": "interface",
        "public_methods": [
          "siteIdentifier",
          "actorId",
          "organizationIdentifier",
          "workspaceIdentifier",
          "requestId",
          "correlationId",
          "deliverySurface"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Application\\ExtensionServiceProvider",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Application\\ExtensionServiceProvider",
        "source_path": "src/Spi/Application/ExtensionServiceProvider.php",
        "target_path": "src/Spi/Application/ExtensionServiceProvider.php",
        "kind": "interface",
        "public_methods": [
          "register"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Binding\\ExecutableBindingKind",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Binding\\ExecutableBindingKind",
        "source_path": "src/Spi/Binding/ExecutableBindingKind.php",
        "target_path": "src/Spi/Binding/ExecutableBindingKind.php",
        "kind": "enum",
        "public_methods": [
          "cases",
          "from",
          "tryFrom"
        ],
        "public_properties": [
          "name",
          "value"
        ],
        "public_constants": [
          "FieldPresenter",
          "MoneyRateProvider",
          "UnitConversionProvider",
          "CustomBusinessViewHandler",
          "CustomBusinessActionHandler",
          "AdministratorRoute",
          "PortalRoute",
          "DomainListener",
          "EventConsumer",
          "JobHandler",
          "Projection",
          "Webhook",
          "StudioPreviewRenderer"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Binding\\ExecutableBindingRequirements",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Binding\\ExecutableBindingRequirements",
        "source_path": "src/Spi/Binding/ExecutableBindingRequirements.php",
        "target_path": "src/Spi/Binding/ExecutableBindingRequirements.php",
        "kind": "class",
        "public_methods": [
          "fromManifestContributions",
          "identifiers",
          "assertDeclared",
          "assertSatisfied",
          "toArray"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Binding\\ExtensionBindingProvider",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Binding\\ExtensionBindingProvider",
        "source_path": "src/Spi/Binding/ExtensionBindingProvider.php",
        "target_path": "src/Spi/Binding/ExtensionBindingProvider.php",
        "kind": "interface",
        "public_methods": [
          "bind"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Binding\\ExtensionBindingRegistrar",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Binding\\ExtensionBindingRegistrar",
        "source_path": "src/Spi/Binding/ExtensionBindingRegistrar.php",
        "target_path": "src/Spi/Binding/ExtensionBindingRegistrar.php",
        "kind": "interface",
        "public_methods": [
          "fieldPresenter",
          "moneyRateProvider",
          "unitConversionProvider",
          "customBusinessViewHandler",
          "customBusinessActionHandler",
          "administratorRoute",
          "portalRoute",
          "domainListener",
          "eventConsumer",
          "jobHandler",
          "projection",
          "webhook",
          "studioPreviewRenderer"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Binding\\Http\\AdministratorRouteHandlerFactory",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Binding\\Http\\AdministratorRouteHandlerFactory",
        "source_path": "src/Spi/Binding/Http/AdministratorRouteHandlerFactory.php",
        "target_path": "src/Spi/Binding/Http/AdministratorRouteHandlerFactory.php",
        "kind": "interface",
        "public_methods": [
          "create"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Binding\\Http\\AdministratorRouteRenderer",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Binding\\Http\\AdministratorRouteRenderer",
        "source_path": "src/Spi/Binding/Http/AdministratorRouteRenderer.php",
        "target_path": "src/Spi/Binding/Http/AdministratorRouteRenderer.php",
        "kind": "interface",
        "public_methods": [
          "render"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Binding\\Http\\PortalRouteHandlerFactory",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Binding\\Http\\PortalRouteHandlerFactory",
        "source_path": "src/Spi/Binding/Http/PortalRouteHandlerFactory.php",
        "target_path": "src/Spi/Binding/Http/PortalRouteHandlerFactory.php",
        "kind": "interface",
        "public_methods": [
          "create"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Binding\\Http\\PortalRouteRenderer",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Binding\\Http\\PortalRouteRenderer",
        "source_path": "src/Spi/Binding/Http/PortalRouteRenderer.php",
        "target_path": "src/Spi/Binding/Http/PortalRouteRenderer.php",
        "kind": "interface",
        "public_methods": [
          "render"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\BusinessIntegration\\Application\\DomainEventHandler",
        "new_fqcn": "Kumwe\\Extension\\Spi\\BusinessIntegration\\Application\\DomainEventHandler",
        "source_path": "src/Spi/BusinessIntegration/Application/DomainEventHandler.php",
        "target_path": "src/Spi/BusinessIntegration/Application/DomainEventHandler.php",
        "kind": "interface",
        "public_methods": [
          "handle"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\BusinessIntegration\\Application\\IntegrationEventHandler",
        "new_fqcn": "Kumwe\\Extension\\Spi\\BusinessIntegration\\Application\\IntegrationEventHandler",
        "source_path": "src/Spi/BusinessIntegration/Application/IntegrationEventHandler.php",
        "target_path": "src/Spi/BusinessIntegration/Application/IntegrationEventHandler.php",
        "kind": "interface",
        "public_methods": [
          "handle"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\BusinessIntegration\\Application\\IntegrationEventTransport",
        "new_fqcn": "Kumwe\\Extension\\Spi\\BusinessIntegration\\Application\\IntegrationEventTransport",
        "source_path": "src/Spi/BusinessIntegration/Application/IntegrationEventTransport.php",
        "target_path": "src/Spi/BusinessIntegration/Application/IntegrationEventTransport.php",
        "kind": "interface",
        "public_methods": [
          "publish"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordPage",
        "new_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordPage",
        "source_path": "src/Spi/BusinessRecord/Application/BusinessRecordPage.php",
        "target_path": "src/Spi/BusinessRecord/Application/BusinessRecordPage.php",
        "kind": "interface",
        "public_methods": [
          "records",
          "nextCursor",
          "aggregates"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordQueryPurpose",
        "new_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordQueryPurpose",
        "source_path": "src/Spi/BusinessRecord/Application/BusinessRecordQueryPurpose.php",
        "target_path": "src/Spi/BusinessRecord/Application/BusinessRecordQueryPurpose.php",
        "kind": "enum",
        "public_methods": [
          "cases",
          "from",
          "tryFrom"
        ],
        "public_properties": [
          "name",
          "value"
        ],
        "public_constants": [
          "Browse",
          "Report",
          "Export"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordReadRequest",
        "new_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordReadRequest",
        "source_path": "src/Spi/BusinessRecord/Application/BusinessRecordReadRequest.php",
        "target_path": "src/Spi/BusinessRecord/Application/BusinessRecordReadRequest.php",
        "kind": "class",
        "public_methods": [
          "__construct"
        ],
        "public_properties": [
          "context",
          "definitionIdentifier",
          "specification",
          "organizationIdentifier",
          "purpose"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordReader",
        "new_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordReader",
        "source_path": "src/Spi/BusinessRecord/Application/BusinessRecordReader.php",
        "target_path": "src/Spi/BusinessRecord/Application/BusinessRecordReader.php",
        "kind": "interface",
        "public_methods": [
          "readPage"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordView",
        "new_fqcn": "Kumwe\\Extension\\Spi\\BusinessRecord\\Application\\BusinessRecordView",
        "source_path": "src/Spi/BusinessRecord/Application/BusinessRecordView.php",
        "target_path": "src/Spi/BusinessRecord/Application/BusinessRecordView.php",
        "kind": "interface",
        "public_methods": [
          "definitionIdentifier",
          "definitionVersion",
          "recordIdentifier",
          "version",
          "siteIdentifier",
          "organizationIdentifier",
          "workflowState",
          "values",
          "updatedAt"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CanonicalCompositionDocument",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CanonicalCompositionDocument",
        "source_path": "src/Spi/Contribution/CanonicalCompositionDocument.php",
        "target_path": "src/Spi/Contribution/CanonicalCompositionDocument.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "identifier",
          "identity",
          "document",
          "toArray"
        ],
        "public_properties": [
          "kind",
          "canonical"
        ],
        "public_constants": [
          "MAXIMUM_CANONICAL_BYTES"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CanonicalCompositionKind",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CanonicalCompositionKind",
        "source_path": "src/Spi/Contribution/CanonicalCompositionKind.php",
        "target_path": "src/Spi/Contribution/CanonicalCompositionKind.php",
        "kind": "enum",
        "public_methods": [
          "identityMember",
          "cases",
          "from",
          "tryFrom"
        ],
        "public_properties": [
          "name",
          "value"
        ],
        "public_constants": [
          "BlockDefinition",
          "Pattern",
          "FieldAdapter",
          "Inspector",
          "DesignVocabulary",
          "Migration"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionBlockDeclaration",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionBlockDeclaration",
        "source_path": "src/Spi/Contribution/CompositionBlockDeclaration.php",
        "target_path": "src/Spi/Contribution/CompositionBlockDeclaration.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "identifier",
          "renderer",
          "version",
          "toArray",
          "fromArray"
        ],
        "public_properties": [
          "slots",
          "properties"
        ],
        "public_constants": [
          "MAXIMUM_SLOTS"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionDesignVocabularyDeclaration",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionDesignVocabularyDeclaration",
        "source_path": "src/Spi/Contribution/CompositionDesignVocabularyDeclaration.php",
        "target_path": "src/Spi/Contribution/CompositionDesignVocabularyDeclaration.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "identifier",
          "version",
          "toArray",
          "fromArray"
        ],
        "public_properties": [
          "tokens",
          "recipes",
          "sizeRoles"
        ],
        "public_constants": [
          "MAXIMUM_TOKENS",
          "MAXIMUM_RECIPES",
          "MAXIMUM_SIZE_ROLES"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionFieldControlDeclaration",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionFieldControlDeclaration",
        "source_path": "src/Spi/Contribution/CompositionFieldControlDeclaration.php",
        "target_path": "src/Spi/Contribution/CompositionFieldControlDeclaration.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "identifier",
          "version",
          "toArray",
          "fromArray"
        ],
        "public_properties": [
          "edits"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionHostBinding",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionHostBinding",
        "source_path": "src/Spi/Contribution/CompositionHostBinding.php",
        "target_path": "src/Spi/Contribution/CompositionHostBinding.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "identifier",
          "toArray"
        ],
        "public_properties": [
          "kind",
          "documentId",
          "renderer",
          "capability"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionInspectorDeclaration",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionInspectorDeclaration",
        "source_path": "src/Spi/Contribution/CompositionInspectorDeclaration.php",
        "target_path": "src/Spi/Contribution/CompositionInspectorDeclaration.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "identifier",
          "block",
          "version",
          "toArray",
          "fromArray"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionMigrationDeclaration",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionMigrationDeclaration",
        "source_path": "src/Spi/Contribution/CompositionMigrationDeclaration.php",
        "target_path": "src/Spi/Contribution/CompositionMigrationDeclaration.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "identifier",
          "block",
          "fromVersion",
          "toVersion",
          "toArray",
          "fromArray"
        ],
        "public_properties": [
          "operations"
        ],
        "public_constants": [
          "MAXIMUM_OPERATIONS",
          "ACTIONS"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionPatternDeclaration",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionPatternDeclaration",
        "source_path": "src/Spi/Contribution/CompositionPatternDeclaration.php",
        "target_path": "src/Spi/Contribution/CompositionPatternDeclaration.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "identifier",
          "version",
          "toArray",
          "fromArray"
        ],
        "public_properties": [
          "blocks"
        ],
        "public_constants": [
          "MAXIMUM_BLOCKS"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionPropertySchema",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionPropertySchema",
        "source_path": "src/Spi/Contribution/CompositionPropertySchema.php",
        "target_path": "src/Spi/Contribution/CompositionPropertySchema.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "toArray",
          "fromArray"
        ],
        "public_properties": [
          "properties"
        ],
        "public_constants": [
          "MAXIMUM_PROPERTIES",
          "MAXIMUM_STRING_LENGTH",
          "MAXIMUM_TEXT_LENGTH",
          "MAXIMUM_CHOICE_VALUES",
          "REFERENCE_KINDS"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionPropertyType",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\CompositionPropertyType",
        "source_path": "src/Spi/Contribution/CompositionPropertyType.php",
        "target_path": "src/Spi/Contribution/CompositionPropertyType.php",
        "kind": "enum",
        "public_methods": [
          "cases",
          "from",
          "tryFrom"
        ],
        "public_properties": [
          "name",
          "value"
        ],
        "public_constants": [
          "String",
          "Text",
          "Integer",
          "Number",
          "Boolean",
          "Choice",
          "Reference"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\TranslationSetItemAssociation",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Contribution\\TranslationSetItemAssociation",
        "source_path": "src/Spi/Contribution/TranslationSetItemAssociation.php",
        "target_path": "src/Spi/Contribution/TranslationSetItemAssociation.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "groupIdForSite",
          "toArray"
        ],
        "public_properties": [
          "owner",
          "translationSet",
          "generation"
        ],
        "public_constants": [
          "GENERATION",
          "GROUP_NAMESPACE"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Http\\ExtensionRequest",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Http\\ExtensionRequest",
        "source_path": "src/Spi/Http/ExtensionRequest.php",
        "target_path": "src/Spi/Http/ExtensionRequest.php",
        "kind": "class",
        "public_methods": [
          "context",
          "csrfToken"
        ],
        "public_properties": [],
        "public_constants": [
          "CONTEXT",
          "CSRF_TOKEN"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Migration\\ExtensionMigration",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Migration\\ExtensionMigration",
        "source_path": "src/Spi/Migration/ExtensionMigration.php",
        "target_path": "src/Spi/Migration/ExtensionMigration.php",
        "kind": "interface",
        "public_methods": [
          "id",
          "up",
          "down"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Migration\\ExtensionTableNames",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Migration\\ExtensionTableNames",
        "source_path": "src/Spi/Migration/ExtensionTableNames.php",
        "target_path": "src/Spi/Migration/ExtensionTableNames.php",
        "kind": "interface",
        "public_methods": [
          "raw",
          "quoted"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Runtime\\BootableExtension",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Runtime\\BootableExtension",
        "source_path": "src/Spi/Runtime/BootableExtension.php",
        "target_path": "src/Spi/Runtime/BootableExtension.php",
        "kind": "interface",
        "public_methods": [
          "boot"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Runtime\\ExtensionContainer",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Runtime\\ExtensionContainer",
        "source_path": "src/Spi/Runtime/ExtensionContainer.php",
        "target_path": "src/Spi/Runtime/ExtensionContainer.php",
        "kind": "interface",
        "public_methods": [
          "get",
          "share"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Runtime\\ExtensionEvent",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Runtime\\ExtensionEvent",
        "source_path": "src/Spi/Runtime/ExtensionEvent.php",
        "target_path": "src/Spi/Runtime/ExtensionEvent.php",
        "kind": "interface",
        "public_methods": [
          "getName",
          "getArgument",
          "isStopped",
          "stopPropagation"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Studio\\Application\\Preview\\StudioPreviewBindingResult",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Studio\\Application\\Preview\\StudioPreviewBindingResult",
        "source_path": "src/Spi/Studio/Application/Preview/StudioPreviewBindingResult.php",
        "target_path": "src/Spi/Studio/Application/Preview/StudioPreviewBindingResult.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "unavailable",
          "hidden"
        ],
        "public_properties": [
          "available",
          "hidden",
          "value"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Studio\\Application\\Preview\\StudioPreviewBlock",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Studio\\Application\\Preview\\StudioPreviewBlock",
        "source_path": "src/Spi/Studio/Application/Preview/StudioPreviewBlock.php",
        "target_path": "src/Spi/Studio/Application/Preview/StudioPreviewBlock.php",
        "kind": "interface",
        "public_methods": [
          "id",
          "type",
          "version",
          "property"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Studio\\Application\\Preview\\StudioPreviewBlockFragment",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Studio\\Application\\Preview\\StudioPreviewBlockFragment",
        "source_path": "src/Spi/Studio/Application/Preview/StudioPreviewBlockFragment.php",
        "target_path": "src/Spi/Studio/Application/Preview/StudioPreviewBlockFragment.php",
        "kind": "class",
        "public_methods": [
          "__construct"
        ],
        "public_properties": [
          "layoutAttributes",
          "element",
          "className",
          "text",
          "hidden"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Spi\\Studio\\Application\\Preview\\StudioPreviewBlockRenderer",
        "new_fqcn": "Kumwe\\Extension\\Spi\\Studio\\Application\\Preview\\StudioPreviewBlockRenderer",
        "source_path": "src/Spi/Studio/Application/Preview/StudioPreviewBlockRenderer.php",
        "target_path": "src/Spi/Studio/Application/Preview/StudioPreviewBlockRenderer.php",
        "kind": "interface",
        "public_methods": [
          "render"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\ComponentScaffolder",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\ComponentScaffolder",
        "source_path": "src/Toolchain/ComponentScaffolder.php",
        "target_path": "src/Toolchain/ComponentScaffolder.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "scaffold"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\ConformanceReport",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\ConformanceReport",
        "source_path": "src/Toolchain/ConformanceReport.php",
        "target_path": "src/Toolchain/ConformanceReport.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "conforms",
          "toArray"
        ],
        "public_properties": [
          "inspection",
          "checks",
          "findings"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\DeterministicPackageBuilder",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\DeterministicPackageBuilder",
        "source_path": "src/Toolchain/DeterministicPackageBuilder.php",
        "target_path": "src/Toolchain/DeterministicPackageBuilder.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "build"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\ExtensionConformanceTestCase",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\ExtensionConformanceTestCase",
        "source_path": "src/Toolchain/ExtensionConformanceTestCase.php",
        "target_path": "src/Toolchain/ExtensionConformanceTestCase.php",
        "kind": "class",
        "public_methods": [],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\ExtensionLifecycleTestCase",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\ExtensionLifecycleTestCase",
        "source_path": "src/Toolchain/ExtensionLifecycleTestCase.php",
        "target_path": "src/Toolchain/ExtensionLifecycleTestCase.php",
        "kind": "class",
        "public_methods": [
          "testExtensionLifecycleConformance"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\ExtensionPackageConformance",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\ExtensionPackageConformance",
        "source_path": "src/Toolchain/ExtensionPackageConformance.php",
        "target_path": "src/Toolchain/ExtensionPackageConformance.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "withProductionDefaults",
          "run",
          "runLifecycle"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\LifecycleConformanceAdapter",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\LifecycleConformanceAdapter",
        "source_path": "src/Toolchain/LifecycleConformanceAdapter.php",
        "target_path": "src/Toolchain/LifecycleConformanceAdapter.php",
        "kind": "interface",
        "public_methods": [
          "assertPackageSafetyAndSigning",
          "assertSchemaPlan",
          "install",
          "assertDefinitions",
          "assertAuthorizationAndFieldPolicies",
          "assertRoutes",
          "assertRestAndOpenApi",
          "assertCliAndMcp",
          "assertJobsEventsAndReports",
          "assertPortalAndAdministrator",
          "assertBackupAndRestore",
          "upgrade",
          "disable",
          "reactivate",
          "assertDatabaseMatrix",
          "uninstall",
          "recover"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\LifecycleConformanceReport",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\LifecycleConformanceReport",
        "source_path": "src/Toolchain/LifecycleConformanceReport.php",
        "target_path": "src/Toolchain/LifecycleConformanceReport.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "conforms",
          "toArray"
        ],
        "public_properties": [
          "checks",
          "violations"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\LifecycleConformanceRunner",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\LifecycleConformanceRunner",
        "source_path": "src/Toolchain/LifecycleConformanceRunner.php",
        "target_path": "src/Toolchain/LifecycleConformanceRunner.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "run"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\PackageBuildResult",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\PackageBuildResult",
        "source_path": "src/Toolchain/PackageBuildResult.php",
        "target_path": "src/Toolchain/PackageBuildResult.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "toArray"
        ],
        "public_properties": [
          "archive",
          "inspection"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\PackageInspection",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\PackageInspection",
        "source_path": "src/Toolchain/PackageInspection.php",
        "target_path": "src/Toolchain/PackageInspection.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "toArray"
        ],
        "public_properties": [
          "package"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\PackageInspector",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\PackageInspector",
        "source_path": "src/Toolchain/PackageInspector.php",
        "target_path": "src/Toolchain/PackageInspector.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "inspect",
          "limits"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\PackageSigner",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\PackageSigner",
        "source_path": "src/Toolchain/PackageSigner.php",
        "target_path": "src/Toolchain/PackageSigner.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "sign",
          "write"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\ProtectedSigningKeyReader",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\ProtectedSigningKeyReader",
        "source_path": "src/Toolchain/ProtectedSigningKeyReader.php",
        "target_path": "src/Toolchain/ProtectedSigningKeyReader.php",
        "kind": "class",
        "public_methods": [
          "read"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\ScaffoldRequest",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\ScaffoldRequest",
        "source_path": "src/Toolchain/ScaffoldRequest.php",
        "target_path": "src/Toolchain/ScaffoldRequest.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "contributionNamespace"
        ],
        "public_properties": [
          "identifier",
          "version",
          "label",
          "phpNamespace",
          "targetDirectory"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\ScaffoldResult",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\ScaffoldResult",
        "source_path": "src/Toolchain/ScaffoldResult.php",
        "target_path": "src/Toolchain/ScaffoldResult.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "toArray"
        ],
        "public_properties": [
          "directory",
          "fileCount"
        ],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\SignatureDocument",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\SignatureDocument",
        "source_path": "src/Toolchain/SignatureDocument.php",
        "target_path": "src/Toolchain/SignatureDocument.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "fromJson",
          "toArray",
          "toJson"
        ],
        "public_properties": [
          "keyId",
          "packageSha256",
          "base64Signature"
        ],
        "public_constants": [
          "FORMAT"
        ],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      },
      {
        "old_fqcn": "Kumwe\\Extension\\Toolchain\\StaticConformanceRunner",
        "new_fqcn": "Kumwe\\Extension\\Toolchain\\StaticConformanceRunner",
        "source_path": "src/Toolchain/StaticConformanceRunner.php",
        "target_path": "src/Toolchain/StaticConformanceRunner.php",
        "kind": "class",
        "public_methods": [
          "__construct",
          "run"
        ],
        "public_properties": [],
        "public_constants": [],
        "exceptions": [
          "Declared parameter, filesystem, archive, cryptographic and domain refusals are preserved in source PHPDoc and docs/public-api.md."
        ],
        "serialization_contract": "Existing manifest generations, canonical byte records and public value/report shapes remain SDK-owned; see resources/contract/generations.json and source-derived API reference.",
        "compatibility": "Canonical SDK export retains its exact name and public member contract."
      }
    ],
    "consumers": {
      "app_code": [
        "Future adoption must review canonical-package-migration.json and current App imports; no current App inspection/adoption is claimed here."
      ],
      "configuration_and_di": [
        "Explicit host construction or host-owned factories for the encoder, inspector, signing and lifecycle collaborators."
      ],
      "reflection_and_string_references": [
        "Preserve SDK classification/generation records and profile paths; adopt standard v1 manifests for governance evidence."
      ],
      "fixtures_and_examples": [
        "resources/fixtures/generations",
        "resources/extension-scaffold/complete-component",
        "examples/direct-construction.php"
      ],
      "external": [
        "Extension package authors",
        "SDK CLI callers",
        "Independent published package verification",
        "Future App composition"
      ]
    },
    "dependency_injection": {
      "mode": "direct",
      "provider": null,
      "factories": [],
      "aliases": [],
      "service_lifetimes": [
        "Per-operation authoring tools; host owns encoder, signing-key and lifecycle collaborator scopes and concurrency.",
        "The CLI constructs a new bounded toolchain per invocation and verifies its expected native tuple."
      ],
      "configuration_keys": [],
      "provider_absence_reason": "The SDK owns authoring contracts and per-operation tools, not an application service container. Callers explicitly supply the canonical encoder, bounded inspector, signing-key reader and lifecycle collaborators. No safe default can choose a compatible native tuple, protected signing key, host trust policy or execution scope. The CLI is an explicit per-invocation composition root. Hosts may wrap these constructors in their own factories; this package registers no ambient services or empty provider."
    }
  },
  "native_cpp": null,
  "php_extension": null,
  "tests": {
    "moved_or_added": [
      "Existing tests retain package-owned behavior, boundaries and conformance; see tests/ownership.json.",
      "tools/public-api.php and tools/verify-v2-metadata.php verify every declared API member, capability and release-record identity.",
      "tools/verify-clean-consumer.php and tools/verify-scaffold-consumer.php prove actual archived SDK/generated production installs.",
      "tools/verify-package-set-consumer.php checks the complete verified archive graph including an actual empty-vendor offline replay."
    ],
    "remain_in_app_or_consumer": [
      "Admission, authorization, activation, delivery, trust policy, storage and host transaction/lifecycle integration."
    ],
    "split_tests": [
      "Package-owned portable behavior stays in its canonical owner; SDK tests cover author binding and package/toolchain behavior."
    ],
    "prohibited_duplicates": [
      "Do not reintroduce removed 88 capability definitions or duplicate owner algorithms under the SDK namespace."
    ],
    "corpora": [
      "resources/fixtures/generations/manifest-1 through manifest-6",
      "resources/contract/generations.json",
      "resources/extension-scaffold/complete-component"
    ]
  },
  "documentation": {
    "charter": "CHARTER.md",
    "readme": "README.md",
    "public_api": "docs/public-api.md",
    "architecture": "docs/architecture.md",
    "integration_or_consumer": "docs/host-integration.md",
    "examples": [
      "examples/direct-construction.php"
    ],
    "changelog_record": "CHANGELOG.md 0.3.2"
  },
  "release_expectations": {
    "version_policy": "Exact pre-1.0 canonical dependency pins; SDK 0.3 preserves the existing 96 owned public exports while removing duplicate declarations now owned by extracted packages.",
    "expected_artifact_types": [
      "GitHub source ZIP",
      "Composer archive"
    ],
    "required_checks": [
      "composer check",
      "Released dependency no-dev archive consumer without native runtime",
      "Native source-candidate and generated-scaffold consumer gates",
      "Release automation and source/archive integrity regressions",
      "Independent stable source/tag/registry/archive verification",
      "Combined 31-package no-dev/offline graph verification after actual SDK publication"
    ],
    "required_registry_or_installer": "Packagist kumwe/extension-sdk",
    "required_external_attestation": true
  },
  "governance": {
    "completion_claim": false
  },
  "decisions": [
    "All 96 exports remain governed; exactly two documented PHPUnit bridge types are optional for no-dev consumers.",
    "Runtime and generated production PHP APIs require an injected canonical encoder contract; the optional CLI and development toolchain use the verified native adapter.",
    "Direct construction expresses operation and authority boundaries; no ambient provider is registered.",
    "This source record describes the package contract; independent release attestations identify actual published bytes."
  ],
  "blockers": [],
  "consumer_contract": {
    "permitted_only_when": [
      "All selected stable PHP archives and the Engine/binding/Computation native successor have independent release evidence.",
      "The external release attestation identifies the exact SDK archive and coherent dependency graph."
    ],
    "consumer_repository": "https://github.com/kumwe/app",
    "dependency_or_native_change": "Install the independently qualified exact SDK/package graph and provision the qualified native tuple for the optional CLI and development toolchain.",
    "namespace_or_api_replacements": [
      "Review docs/canonical-package-migration.json for duplicate SDK symbols moved to their canonical owners; retain all 96 current SDK exports."
    ],
    "files_to_update": [
      "Consumer composer.json and composer.lock",
      "Consumer imports, explicit service composition and capability evidence"
    ],
    "files_to_remove": [],
    "tests_to_remove": [],
    "tests_to_retain_or_add": [
      "All host-owned admission, authorization, activation, persistence and delivery integration tests",
      "Source/archive identity and generated extension consumer tests",
      "No duplicate canonical package algorithms or namespace aliases"
    ],
    "di_or_provisioning_changes": [
      "Explicitly inject encoder/toolchain collaborators and verify native tuple at host composition boundaries."
    ],
    "capability_index_changes": [
      "Record the verified owner, release and manifest coordinates in the consumer capability index."
    ],
    "changelog_and_evidence_changes": [
      "Record package publication, independent release verification and Core integration as separate observations."
    ],
    "verification_commands": [
      "composer validate --strict",
      "composer check",
      "php tools/verify-clean-consumer.php --scaffold"
    ]
  }
}
---

# SDK release contract record

This machine-readable record binds the SDK public manifests, exported symbols, dependency
injection, test ownership and consumer obligations. Source baselines and the two attestation
identifiers preserve verification provenance. Current publication state is observed through
[GitHub releases](https://github.com/kumwe/extension-sdk/releases) and
[Packagist](https://packagist.org/packages/kumwe/extension-sdk).

The [host contract](host-integration.md) and [App agreement](app-agreement.md) define current
consumer responsibilities. [Release qualification](release-qualification.md) defines the
independent source, archive and graph checks. Actual release identity and results belong in
external attestations; this record cannot attest to its own publication or Core integration.
