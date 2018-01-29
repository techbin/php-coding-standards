<?php declare(strict_types=1); # -*- coding: utf-8 -*-
/*
 * This file is part of the php-coding-standards package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This file contains code from "phpcs-calisthenics-rules" repository
 * found at https://github.com/object-calisthenics
 * Copyright (c) 2014 Doctrine Project
 * released under MIT license.
 */

namespace Inpsyde\CodingStandard\Sniffs\CodeQuality;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * @package php-coding-standards
 * @license http://opensource.org/licenses/MIT MIT
 */
final class MaxNestingLevelSniff implements Sniff
{
    /**
     * @var string
     */
    const ERROR_MESSAGE = 'Only %d indentation level%s per function/method. Found %s levels.';

    /**
     * @var int
     */
    public $maxNestingLevel = 2;

    /**
     * @var File
     */
    private $file;

    /**
     * @var int
     */
    private $position;

    /**
     * @var int
     */
    private $nestingLevel;

    /**
     * @var mixed[]
     */
    private $ignoredScopeStack = [];

    /**
     * @var int
     */
    private $currentPtr;

    /**
     * @return int[]
     */
    public function register(): array
    {
        return [T_FUNCTION, T_CLOSURE];
    }

    /**
     * @param File $file
     * @param int $position
     */
    public function process(File $file, $position)
    {
        $this->file = $file;
        $this->position = $position;
        $this->nestingLevel = 0;
        $this->ignoredScopeStack = [];

        $tokens = $file->getTokens();
        $token = $tokens[$position];

        // Ignore abstract methods.
        if (isset($token['scope_opener']) === false) {
            return;
        }

        $this->iterateTokens($token['scope_opener'], $token['scope_closer'],
            $tokens);

        $this->nestingLevel = $this->subtractFunctionNestingLevel($token);
        $this->handleNestingLevel($this->nestingLevel);
    }

    private function handleNestingLevel(int $nestingLevel)
    {
        if ($nestingLevel > $this->maxNestingLevel) {
            $levelPluralization = $this->maxNestingLevel > 1 ? 's' : '';

            $error = sprintf(
                self::ERROR_MESSAGE,
                $this->maxNestingLevel,
                $levelPluralization,
                $nestingLevel
            );

            $this->file->addError($error, $this->position, 'TooNested');
        }
    }

    /**
     * @param mixed[] $tokens
     */
    private function iterateTokens(int $start, int $end, array $tokens)
    {
        $this->currentPtr = $start + 1;

        // Find the maximum nesting level of any token in the function.
        for ($this->currentPtr = ($start + 1); $this->currentPtr < $end; ++$this->currentPtr) {
            $nestedToken = $tokens[$this->currentPtr];

            $this->handleToken($nestedToken);
        }
    }

    /**
     * @param mixed[] $nestedToken
     */
    private function handleToken(array $nestedToken)
    {
        $this->handleClosureToken($nestedToken);
        $this->handleCaseToken($nestedToken);

        $this->adjustNestingLevelToIgnoredScope();

        // Calculate nesting level
        $level = $nestedToken['level'] - count($this->ignoredScopeStack);

        if ($this->nestingLevel < $level) {
            $this->nestingLevel = $level;
        }
    }

    /**
     * @param mixed[] $token
     * @return int
     */
    private function subtractFunctionNestingLevel(array $token): int
    {
        return $this->nestingLevel - $token['level'] - 1;
    }

    /**
     * @param mixed[] $nestedToken
     */
    private function handleClosureToken(array $nestedToken)
    {
        if ($nestedToken['code'] === T_CLOSURE) {
            // Move index pointer in case we found a lambda function
            // (another call process will deal with its check later).
            $this->currentPtr = $nestedToken['scope_closer'];

            return;
        }
    }

    /**
     * @param mixed[] $nestedToken
     */
    private function handleCaseToken(array $nestedToken)
    {
        if (in_array($nestedToken['code'], [T_CASE, T_DEFAULT])) {
            array_push($this->ignoredScopeStack, $nestedToken);

            return;
        }
    }

    private function adjustNestingLevelToIgnoredScope()
    {
        // Iterated through ignored scope stack to find out if
        // anything can be popped out and adjust nesting level.
        foreach ($this->ignoredScopeStack as $key => $ignoredScope) {
            $this->unsetScopeIfNotCurrent($key, $ignoredScope);
        }
    }

    /**
     * @param int $key
     * @param mixed[] $ignoredScope
     */
    private function unsetScopeIfNotCurrent(int $key, array $ignoredScope)
    {
        if ($ignoredScope['scope_closer'] !== $this->currentPtr) {
            return;
        }

        unset($this->ignoredScopeStack[$key]);
    }
}
