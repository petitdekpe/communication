<?php

declare(strict_types=1);

namespace App\Twig\Components\Support;

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Déclaration des props des Twig Components, à utiliser dans un hook #[PreMount] :
 * validation (type, valeurs permises, obligatoire) et normalisation (chaîne -> enum, chaîne -> date)
 * avant que les valeurs ne soient affectées aux propriétés typées du composant.
 *
 * Les clés non déclarées (class, data-*, aria-*…) sont laissées telles quelles : elles deviennent
 * les attributs HTML du composant.
 */
final class Props
{
    public static function resolver(): OptionsResolver
    {
        return (new OptionsResolver())->setIgnoreUndefined(true);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function resolve(OptionsResolver $resolver, array $data): array
    {
        return $resolver->resolve($data) + $data;
    }

    public static function requiredString(OptionsResolver $resolver, string ...$options): void
    {
        foreach ($options as $option) {
            $resolver
                ->setRequired($option)
                ->setAllowedTypes($option, 'string')
                ->setAllowedValues($option, static fn (string $value): bool => '' !== trim($value));
        }
    }

    public static function optionalString(OptionsResolver $resolver, string ...$options): void
    {
        foreach ($options as $option) {
            $resolver
                ->setDefault($option, null)
                ->setAllowedTypes($option, ['null', 'string'])
                ->setNormalizer($option, static fn (Options $o, ?string $value): ?string => null === $value || '' === trim($value) ? null : $value);
        }
    }

    public static function bool(OptionsResolver $resolver, string $option, bool $default): void
    {
        $resolver->setDefault($option, $default)->setAllowedTypes($option, 'bool');
    }

    public static function headingLevel(OptionsResolver $resolver, int $default, string $option = 'headingLevel'): void
    {
        $resolver
            ->setDefault($option, $default)
            ->setAllowedTypes($option, 'int')
            ->setAllowedValues($option, [1, 2, 3, 4, 5, 6]);
    }

    /**
     * Prop de type enum : accepte l'instance ou sa valeur (variant="accent").
     *
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     * @param T|null          $default null : prop obligatoire
     */
    public static function enum(OptionsResolver $resolver, string $option, string $enum, ?\BackedEnum $default = null): void
    {
        if (null === $default) {
            $resolver->setRequired($option);
        } else {
            $resolver->setDefault($option, $default);
        }

        $resolver
            ->setAllowedTypes($option, ['string', $enum])
            ->setNormalizer($option, static function (Options $o, string|\BackedEnum $value) use ($option, $enum): \BackedEnum {
                if ($value instanceof $enum) {
                    return $value;
                }

                if (\is_string($value) && null !== $case = $enum::tryFrom($value)) {
                    return $case;
                }

                $allowed = array_map(static fn (\BackedEnum $case): string => (string) $case->value, $enum::cases());

                throw new InvalidOptionsException(\sprintf('La prop "%s" n\'accepte que : %s.', $option, implode(', ', $allowed)));
            });
    }

    /**
     * Prop enum facultative (null par défaut).
     *
     * @param class-string<\BackedEnum> $enum
     */
    public static function optionalEnum(OptionsResolver $resolver, string $option, string $enum): void
    {
        $resolver
            ->setDefault($option, null)
            ->setAllowedTypes($option, ['null', 'string', $enum])
            ->setNormalizer($option, static function (Options $o, string|\BackedEnum|null $value) use ($option, $enum): ?\BackedEnum {
                if (null === $value || $value instanceof $enum) {
                    return $value;
                }

                if (\is_string($value) && null !== $case = $enum::tryFrom($value)) {
                    return $case;
                }

                $allowed = array_map(static fn (\BackedEnum $case): string => (string) $case->value, $enum::cases());

                throw new InvalidOptionsException(\sprintf('La prop "%s" n\'accepte que : %s.', $option, implode(', ', $allowed)));
            });
    }

    /**
     * Prop date : \DateTimeInterface ou chaîne lisible par \DateTimeImmutable ('2026-10-06').
     */
    public static function date(OptionsResolver $resolver, string $option, bool $required = true): void
    {
        if ($required) {
            $resolver->setRequired($option);
        } else {
            $resolver->setDefault($option, null);
        }

        $resolver
            ->setAllowedTypes($option, $required ? ['string', \DateTimeInterface::class] : ['null', 'string', \DateTimeInterface::class])
            ->setNormalizer($option, static function (Options $o, string|\DateTimeInterface|null $value) use ($option): ?\DateTimeInterface {
                if (null === $value || $value instanceof \DateTimeInterface) {
                    return $value;
                }

                try {
                    return new \DateTimeImmutable($value);
                } catch (\Exception $e) {
                    throw new InvalidOptionsException(\sprintf('La prop "%s" attend une date valide, "%s" reçu.', $option, $value), previous: $e);
                }
            });
    }

    /**
     * Deux props qui vont ensemble (libellé + lien) : toutes deux renseignées ou toutes deux absentes.
     *
     * @param array<string, mixed> $resolved
     */
    public static function assertPaired(array $resolved, string $first, string $second): void
    {
        if ((null === $resolved[$first]) !== (null === $resolved[$second])) {
            throw new InvalidOptionsException(\sprintf('Les props "%s" et "%s" doivent être renseignées ensemble.', $first, $second));
        }
    }
}
