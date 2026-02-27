<?php

declare(strict_types=1);

use ObjectCalisthenics\Sniffs\Files\ClassLengthSniff;
use ObjectCalisthenics\Sniffs\Metrics\CyclomaticComplexitySniff;
use ObjectCalisthenics\Sniffs\Methods\MethodLengthSniff;
use PhpMd\Rule\ExcessivePublicCount;
use PhpMd\Rule\TooManyMethods;
use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\UnusedFunctionParameterSniff;
use PHP_CodeSniffer\Standards\PSR1\Sniffs\Files\SideEffectsSniff;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Methods\MethodDeclarationSniff;
use SlevomatCodingStandard\Sniffs\Classes\ForbiddenPublicPropertySniff;
use SlevomatCodingStandard\Sniffs\Functions\DisallowEmptySniff;
use SlevomatCodingStandard\Sniffs\Namespaces\UnusedUsesSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DisallowMixedTypeHintSniff;

return [
    'preset' => 'symfony',
    'ide' => null,
    'exclude' => [
        'vendor',
        'var',
        'node_modules',
        'migrations',
        'tests',
    ],
    'add' => [],
    'remove' => [],
    'config' => [
        // ====[1] FILES & SIDE EFFECTS ====
        SideEffectsSniff::class => [
            'exclude' => [
                'config/bundles.php',
                'public/index.php',
                'src/Kernel.php',
            ],
        ],
        
        // ====[2] DOMAIN LAYER: Strict Standards (Pure Business Logic) ====
        UnusedUsesSniff::class => [
            'searchAnnotations' => true,
        ],
        
        // ====[3] CLASSES & SCOPE ====
        ForbiddenPublicPropertySniff::class => [
            'exclude' => [
                'src/Application/DTO',  // DTOs may have public immutable properties
            ],
        ],
        
        // ====[4] COMPLEXITY LIMITS (Adapted for DDD) ====
        ClassLengthSniff::class => [
            'maxLength' => 200,  // Controllers & Config classes can be long
            'exclude' => [
                'src/Infrastructure/Config/EvidenceConfig.php',
                'src/Infrastructure/Config/EventDefinitionLoader.php',
                'src/Controller',
            ],
        ],
        
        MethodLengthSniff::class => [
            'maxLength' => 40,  // Validators & Builders can have longer methods
            'exclude' => [
                'src/Application/Validator',
                'src/Domain/Service/JournalBuilder.php',
            ],
        ],
        
        CyclomaticComplexitySniff::class => [
            'maxComplexity' => 7,  // Relaxed for Config & Validators
            'exclude' => [
                'src/Infrastructure/Config',
                'src/Application/Validator',
                'src/Infrastructure/Jira',
                'src/Infrastructure/NetBox',
            ],
        ],
        
        // ====[5] ARCHITECTURE ====
        ExcessivePublicCount::class => [
            'threshold' => 10,  // Allow more in Service/Entity classes
            'exclude' => [
                'src/Application/DTO',
                'src/Domain/ValueObject',
                'src/Infrastructure/Persistence/Entity',
            ],
        ],
        
        TooManyMethods::class => [
            'maxMethods' => 20,  // Controllers & Services can have more methods
            'exclude' => [
                'src/Controller',
                'src/Infrastructure',
                'src/Application/UseCase',
            ],
        ],
        
        // ====[6] TYPE HINTS & ANNOTATIONS ====
        DisallowMixedTypeHintSniff::class => [
            'exclude' => [
                'src/Domain/ValueObject/FormData.php',
                'src/Infrastructure/Config/EventDefinitionLoader.php',
            ],
        ],
        
        // ====[7] CODE STYLE ====
        DisallowEmptySniff::class => [
            'exclude' => [
                'src/Domain/Service/JournalBuilder.php',
            ],
        ],
        
        UnusedFunctionParameterSniff::class => [
            'exclude' => [
                'src/Application/Validator/EventDataValidator.php',
                'src/Infrastructure/Security/UserChecker.php',
            ],
        ],
    ],
    'requirements' => [
        'min-quality' => 70,       // Minimum code quality score
        'min-architecture' => 75,  // Architecture strictness
        'min-style' => 70,         // Code style consistency
    ],
    'threads' => null,
];
