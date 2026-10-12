<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Découpe un corps éditorial (HTML déjà assaini) en une introduction et des sections, une par titre h2,
 * pour que les pages puissent présenter chaque section dans une carte.
 */
final class BodySectionsExtension
{
    /**
     * @return array{intro: string, sections: list<string>}
     */
    #[AsTwigFilter('body_sections', isSafe: ['html'])]
    public function split(string $html): array
    {
        $parts = preg_split('/(?=<h2[\s>])/i', $html, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $intro = '';
        if ([] !== $parts && !preg_match('/^<h2[\s>]/i', $parts[0])) {
            $intro = trim((string) array_shift($parts));
        }

        return ['intro' => $intro, 'sections' => array_map('trim', $parts)];
    }

    /**
     * Extrait la liste de définitions (<dl>) d'une section : le HTML avant et après la liste, et les paires
     * titre / texte. Si les titres sont numérotés (« 1. », « Action 1 : »), le numéro est retiré et `numbered`
     * est vrai : la carte affiche alors son rang. Sans numéro dans le contenu, les cartes n'en affichent pas.
     *
     * @return array{before: string, items: list<array{title: string, text: string, href: string|null}>, after: string, numbered: bool}|null
     */
    #[AsTwigFilter('definition_cards', isSafe: ['html'])]
    public function definitionCards(string $html): ?array
    {
        if (!preg_match('~^(.*?)<dl[^>]*>(.*?)</dl>(.*)$~is', $html, $match)) {
            return null;
        }
        if (!preg_match_all('~<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>~is', $match[2], $pairs, PREG_SET_ORDER)) {
            return null;
        }

        $numbered = false;
        $items = array_map(static function (array $pair) use (&$numbered): array {
            $title = trim((string) preg_replace('/^\s*(?:[^\d<]{0,12}\s)?\d+\s*[.:]\s*/u', '', $pair[1], 1, $count));
            $numbered = $numbered || $count > 0;
            // Un titre qui est un lien rend toute la carte cliquable : le lien est porté par la carte.
            $href = preg_match('~<a\s[^>]*href="([^"]*)"~i', $title, $link) ? html_entity_decode($link[1]) : null;

            return ['title' => null === $href ? $title : trim(strip_tags($title)), 'text' => trim($pair[2]), 'href' => $href];
        }, $pairs);

        return ['before' => trim($match[1]), 'items' => $items, 'after' => trim($match[3]), 'numbered' => $numbered];
    }

    /**
     * Découpe une section en fiches : une par titre h3 suivi d'une liste de définitions (fiches projet).
     * Le titre est conservé tel quel ; chaque paire titre / texte de la liste devient un élément de la fiche.
     * Null si la section n'a pas cette forme.
     *
     * @return array{before: string, cards: list<array{title: string, items: list<array{title: string, text: string}>}>}|null
     */
    #[AsTwigFilter('heading_cards', isSafe: ['html'])]
    public function headingCards(string $html): ?array
    {
        $parts = preg_split('/(?=<h3[\s>])/i', $html, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $before = '';
        if ([] !== $parts && !preg_match('/^<h3[\s>]/i', $parts[0])) {
            $before = trim((string) array_shift($parts));
        }
        if ([] === $parts) {
            return null;
        }

        $cards = [];
        foreach ($parts as $part) {
            if (!preg_match('~^<h3[^>]*>(.*?)</h3>.*?<dl[^>]*>(.*?)</dl>~is', $part, $match)
                || !preg_match_all('~<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>~is', $match[2], $pairs, PREG_SET_ORDER)) {
                return null;
            }
            $cards[] = [
                'title' => trim($match[1]),
                'items' => array_map(static fn (array $pair): array => ['title' => trim($pair[1]), 'text' => trim($pair[2])], $pairs),
            ];
        }

        return ['before' => $before, 'cards' => $cards];
    }
}
