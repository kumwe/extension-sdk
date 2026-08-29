<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\Binding;

use InvalidArgumentException;
use Kumwe\Extension\Manifest\ManifestContributions;

/** Exact executable binding inventory derived from one validated signed manifest. @since 0.2.0 */
final readonly class ExecutableBindingRequirements
{
    /**
     * @param  array<string, list<string>>  $requirements  Sorted identifiers keyed by binding-kind value.
     *
     * @since  0.2.0
     */
    private function __construct(private array $requirements)
    {
    }

    /**
     * Build requirements from one canonical manifest contribution set.
     *
     * The contribution set has a private constructor and can only come from the SDK manifest parser, so
     * callers cannot manufacture executable authority by supplying a raw array to this factory.
     *
     * @param   ManifestContributions  $contributions  Canonical contribution set.
     *
     * @return  self  Exact executable inventory.
     *
     * @since   0.2.0
     */
    public static function fromManifestContributions(ManifestContributions $contributions): self
    {
        $graph = $contributions->declarations();
        $administrator = self::object($graph['administrator'] ?? []);
        $portal = self::object($graph['portal'] ?? []);
        $business = self::object($graph['business'] ?? []);
        $integration = self::object($graph['integration'] ?? []);
        $composition = self::object($graph['composition'] ?? []);

        $requirements = [
            ExecutableBindingKind::FieldPresenter->value => self::members($business, 'field_presentations', 'field_type'),
            ExecutableBindingKind::MoneyRateProvider->value => self::members($integration, 'rate_providers', 'provider_id'),
            ExecutableBindingKind::UnitConversionProvider->value => self::members($integration, 'unit_converters', 'provider_id'),
            ExecutableBindingKind::CustomBusinessViewHandler->value => self::members($business, 'view_handlers', 'handler'),
            ExecutableBindingKind::CustomBusinessActionHandler->value => self::members($business, 'action_handlers', 'handler'),
            ExecutableBindingKind::AdministratorRoute->value => self::members($administrator, 'routes', 'name'),
            ExecutableBindingKind::PortalRoute->value => self::members($portal, 'routes', 'name'),
            ExecutableBindingKind::DomainListener->value => self::members($integration, 'domain_listeners', 'listener_id'),
            ExecutableBindingKind::EventConsumer->value => self::members($integration, 'consumers', 'consumer_id'),
            ExecutableBindingKind::JobHandler->value => self::members($integration, 'jobs', 'job_type'),
            ExecutableBindingKind::Projection->value => self::members($integration, 'projections', 'identifier'),
            ExecutableBindingKind::Webhook->value => self::members($integration, 'webhooks', 'adapter_id'),
            ExecutableBindingKind::StudioPreviewRenderer->value => self::renderers($composition),
        ];

        return new self(array_filter($requirements, static fn (array $ids): bool => $ids !== []));
    }

    /**
     * Return required identifiers for one executable kind.
     *
     * @param   ExecutableBindingKind  $kind  Executable surface.
     *
     * @return  list<string>  Sorted required identifiers.
     *
     * @since   0.2.0
     */
    public function identifiers(ExecutableBindingKind $kind): array
    {
        return $this->requirements[$kind->value] ?? [];
    }

    /**
     * Refuse an undeclared or wrong-kind binding before implementation storage.
     *
     * @param  ExecutableBindingKind  $kind        Attempted executable surface.
     * @param  string                 $identifier  Attempted signed identifier.
     *
     * @since  0.2.0
     */
    public function assertDeclared(ExecutableBindingKind $kind, string $identifier): void
    {
        if (!in_array($identifier, $this->identifiers($kind), true)) {
            throw new InvalidArgumentException(sprintf(
                'Executable binding %s is undeclared for kind %s.',
                $identifier,
                $kind->value,
            ));
        }
    }

    /**
     * Require exactly one binding for every signed executable identifier and no others.
     *
     * @param  array<string, list<string>>  $bound  Recorded identifiers keyed by kind value.
     *
     * @since  0.2.0
     */
    public function assertSatisfied(array $bound): void
    {
        $normalized = [];
        foreach ($bound as $kind => $identifiers) {
            if (ExecutableBindingKind::tryFrom($kind) === null || !is_array($identifiers) || !array_is_list($identifiers)) {
                throw new InvalidArgumentException('An executable binding inventory contains an unknown kind or shape.');
            }
            $seen = [];
            foreach ($identifiers as $identifier) {
                if (!is_string($identifier) || isset($seen[$identifier])) {
                    throw new InvalidArgumentException('An executable implementation is bound more than once or has no identity.');
                }
                $seen[$identifier] = true;
            }
            $values = array_keys($seen);
            sort($values, SORT_STRING);
            if ($values !== []) {
                $normalized[$kind] = $values;
            }
        }
        ksort($normalized, SORT_STRING);
        $required = $this->requirements;
        ksort($required, SORT_STRING);
        if ($normalized !== $required) {
            throw new InvalidArgumentException('Executable bindings do not exactly satisfy the signed manifest.');
        }
    }

    /** @return array<string, list<string>> Canonical binding inventory. @since 0.2.0 */
    public function toArray(): array
    {
        return $this->requirements;
    }

    /**
     * @param   array<string, mixed>  $section  Contribution section.
     * @param   string                $list     Declaration list member.
     * @param   string                $member   Identifier member.
     *
     * @return  list<string>  Sorted identifiers.
     *
     * @since   0.2.0
     */
    private static function members(array $section, string $list, string $member): array
    {
        $identifiers = [];
        $declarations = $section[$list] ?? [];
        if (!is_array($declarations) || !array_is_list($declarations)) {
            throw new InvalidArgumentException('A validated executable declaration list became malformed.');
        }
        foreach ($declarations as $declaration) {
            if (!is_array($declaration) || !is_string($declaration[$member] ?? null)) {
                throw new InvalidArgumentException('A validated executable declaration became malformed.');
            }
            $identifiers[] = $declaration[$member];
        }
        sort($identifiers, SORT_STRING);

        return $identifiers;
    }

    /**
     * @param   array<string, mixed>  $composition  Validated composition section.
     *
     * @return  list<string>  Sorted renderer identifiers.
     *
     * @since   0.2.0
     */
    private static function renderers(array $composition): array
    {
        $renderers = [];
        $bindings = $composition['host_bindings'] ?? [];
        if (!is_array($bindings) || !array_is_list($bindings)) {
            throw new InvalidArgumentException('A validated composition binding list became malformed.');
        }
        foreach ($bindings as $binding) {
            if (!is_array($binding) || ($binding['kind'] ?? null) !== 'block-definition') {
                continue;
            }
            $renderer = $binding['renderer'] ?? null;
            if (!is_string($renderer)) {
                throw new InvalidArgumentException('A canonical block host binding requires one preview renderer.');
            }
            $renderers[$renderer] = true;
        }
        $identifiers = array_keys($renderers);
        sort($identifiers, SORT_STRING);

        return $identifiers;
    }

    /**
     * @param   mixed  $value  Candidate contribution section.
     *
     * @return  array<string, mixed>  Validated section object.
     *
     * @since   0.2.0
     */
    private static function object(mixed $value): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException('A validated contribution section became malformed.');
        }

        $object = [];
        foreach ($value as $key => $member) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('A validated contribution section requires string keys.');
            }
            $object[$key] = $member;
        }

        return $object;
    }
}
