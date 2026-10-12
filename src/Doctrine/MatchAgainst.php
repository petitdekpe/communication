<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Fonction DQL pour l'index FULLTEXT de MariaDB, toujours en mode booléen :
 *
 *   MATCH_AGAINST(t.title, t.summary, :query) > 0
 *
 * Les colonnes listées doivent être exactement celles de l'index FULLTEXT.
 * La requête doit venir de FulltextQuery : la syntaxe booléenne ne s'applique jamais à une saisie brute.
 */
final class MatchAgainst extends FunctionNode
{
    /** @var list<Node> */
    private array $columns = [];
    private Node $query;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->columns[] = $parser->StateFieldPathExpression();
        while ($parser->getLexer()->isNextToken(TokenType::T_COMMA)) {
            $parser->match(TokenType::T_COMMA);
            $next = $parser->getLexer()->lookahead;
            if (TokenType::T_INPUT_PARAMETER === $next?->type) {
                break;
            }
            $this->columns[] = $parser->StateFieldPathExpression();
        }

        $this->query = $parser->InParameter();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $columns = array_map(static fn (Node $column): string => $column->dispatch($sqlWalker), $this->columns);

        return \sprintf('MATCH(%s) AGAINST (%s IN BOOLEAN MODE)', implode(', ', $columns), $this->query->dispatch($sqlWalker));
    }
}
