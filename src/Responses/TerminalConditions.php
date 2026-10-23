<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Responses;

use TexHub\AlifPay\Http\Response;

/**
 * The Salom conditions of one seller terminal (`GET /conditions/{key}`). A condition id is valid
 * only for the terminal it is listed under.
 *
 * @see https://docs.acquiring.alif.tj/marketplace
 */
final class TerminalConditions
{
    /**
     * @param list<SalomCondition> $conditions
     */
    public function __construct(
        public readonly string $terminalId,
        public readonly array $conditions,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function listFromResponse(Response $response): array
    {
        $list = [];

        foreach ((array) $response->get('payload', []) as $terminal) {
            if (! is_array($terminal)) {
                continue;
            }

            $conditions = [];

            foreach ((array) ($terminal['conditions'] ?? []) as $condition) {
                if (is_array($condition)) {
                    $conditions[] = SalomCondition::fromArray($condition);
                }
            }

            $list[] = new self((string) ($terminal['terminal_id'] ?? ''), $conditions);
        }

        return $list;
    }
}
