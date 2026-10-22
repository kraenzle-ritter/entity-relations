<?php

/*
 * Relations between entities, stored in one polymorphic table.
 *
 * A relation type is stored in one direction only, under its canonical name
 * (the array key). Writing it under its inverse name swaps source and target.
 * Symmetric types are stored once and read from both ends.
 *
 * Hierarchical types run from the narrower entity to the broader one
 * (child -> parent). An entity may have several parents; one edge per type is
 * marked as primary and decides where the entity sits in the tree.
 *
 * The RiC-O mappings are a draft and still have to be checked against
 * RiC-O 1.x before they go into an export.
 */

return [

    'table' => 'relations',

    /*
     * Entity key => model class. The key is what `source_type` and
     * `target_type` hold. The host application fills this in.
     */
    'entities' => [
        // 'keyword' => App\Models\Keyword::class,
    ],

    /*
     * Entity keys for which relations are switched on. The host application
     * may bind its own KraenzleRitter\EntityRelations\Contracts\Gate instead,
     * for example one that reads per-tenant settings. Default: nothing.
     */
    'enabled' => [],

    /*
     * Upper bound for recursive queries. Hierarchies are rarely deeper than
     * ten levels; the bound only keeps faulty data from running away.
     */
    'max_depth' => 64,

    'types' => [

        // general

        'isRelatedTo' => [
            'symmetric' => true,
            'pairs' => ['*:*'],
            'labels' => ['de' => 'siehe auch', 'en' => 'see also', 'fr' => 'voir aussi', 'it' => 'vedi anche'],
            'skos' => 'skos:related',
        ],

        'references' => [
            'inverse' => 'isReferencedBy',
            'pairs' => ['page:*'],
            'labels' => [
                'de' => ['verweist auf', 'wird verwiesen von'],
                'en' => ['references', 'is referenced by'],
            ],
        ],

        // keywords

        'broader' => [
            'inverse' => 'narrower',
            'hierarchical' => true,
            'pairs' => ['keyword:keyword'],
            'labels' => [
                'de' => ['Oberbegriff', 'Unterbegriff'],
                'en' => ['broader term', 'narrower term'],
                'fr' => ['terme générique', 'terme spécifique'],
                'it' => ['termine più ampio', 'termine più specifico'],
            ],
            'skos' => 'skos:broader',
        ],

        'hasOccupation' => [
            'inverse' => 'isOccupationOf',
            'pairs' => ['actor:keyword'],
            'labels' => [
                'de' => ['Tätigkeit oder Beruf', 'Tätigkeit oder Beruf von'],
                'en' => ['occupation', 'occupation of'],
            ],
            'temporal' => true,
        ],

        // actors

        'isParentOf' => [
            'inverse' => 'isChildOf',
            'pairs' => ['actor:actor'],
            'labels' => [
                'de' => ['Elternteil von', 'Kind von'],
                'en' => ['parent of', 'child of'],
            ],
            'rico' => 'rico:hasChild',
        ],

        'isSpouseOf' => [
            'symmetric' => true,
            'pairs' => ['actor:actor'],
            'labels' => ['de' => 'Ehe oder Partnerschaft', 'en' => 'spouse or partner'],
            'temporal' => true,
            'rico' => 'rico:hasOrHadSpouse',
        ],

        'isSiblingOf' => [
            'symmetric' => true,
            'pairs' => ['actor:actor'],
            'labels' => ['de' => 'Geschwister', 'en' => 'sibling'],
            'rico' => 'rico:hasSibling',
        ],

        'hasFamilyRelationWith' => [
            'symmetric' => true,
            'pairs' => ['actor:actor'],
            'labels' => ['de' => 'verwandt', 'en' => 'family relation'],
            'rico' => 'rico:hasFamilyAssociationWith',
        ],

        'isSubordinateTo' => [
            'inverse' => 'hasSubordinate',
            'hierarchical' => true,
            'pairs' => ['actor:actor'],
            'labels' => [
                'de' => ['übergeordnet', 'untergeordnet'],
                'en' => ['subordinate to', 'has subordinate'],
            ],
            'temporal' => true,
            'rico' => 'rico:isOrWasSubordinateTo',
        ],

        'hasSuccessor' => [
            'inverse' => 'isSuccessorOf',
            'pairs' => ['actor:actor', 'place:place'],
            'labels' => [
                'de' => ['Nachfolger', 'Vorgänger'],
                'en' => ['successor', 'predecessor'],
            ],
            'temporal' => true,
            'rico' => 'rico:hasSuccessor',
        ],

        'isMemberOf' => [
            'inverse' => 'hasMember',
            'pairs' => ['actor:actor'],
            'labels' => [
                'de' => ['Mitglied von', 'hat Mitglied'],
                'en' => ['member of', 'has member'],
            ],
            'temporal' => true,
            'rico' => 'rico:isOrWasMemberOf',
        ],

        'leads' => [
            'inverse' => 'isLedBy',
            'pairs' => ['actor:actor'],
            'labels' => [
                'de' => ['leitet', 'geleitet von'],
                'en' => ['leads', 'led by'],
            ],
            'temporal' => true,
            'rico' => 'rico:isOrWasLeaderOf',
        ],

        'worksFor' => [
            'inverse' => 'isEmployerOf',
            'pairs' => ['actor:actor'],
            'labels' => [
                'de' => ['arbeitet für', 'Arbeitgeber von'],
                'en' => ['works for', 'employer of'],
            ],
            'temporal' => true,
            'rico' => 'rico:isOrWasEmployeeOf',
        ],

        'isTeacherOf' => [
            'inverse' => 'isStudentOf',
            'pairs' => ['actor:actor'],
            'labels' => [
                'de' => ['Lehrer von', 'Schüler von'],
                'en' => ['teacher of', 'student of'],
            ],
            'temporal' => true,
            'rico' => 'rico:isOrWasTeacherOf',
        ],

        'knows' => [
            'symmetric' => true,
            'pairs' => ['actor:actor'],
            'labels' => ['de' => 'bekannt mit', 'en' => 'knows'],
            'rico' => 'rico:knows',
        ],

        // actors and places

        'wasBornIn' => [
            'inverse' => 'isBirthPlaceOf',
            'pairs' => ['actor:place'],
            'labels' => [
                'de' => ['geboren in', 'Geburtsort von'],
                'en' => ['born in', 'birthplace of'],
            ],
        ],

        'diedIn' => [
            'inverse' => 'isDeathPlaceOf',
            'pairs' => ['actor:place'],
            'labels' => [
                'de' => ['gestorben in', 'Sterbeort von'],
                'en' => ['died in', 'place of death of'],
            ],
        ],

        'residesIn' => [
            'inverse' => 'isResidenceOf',
            'pairs' => ['actor:place'],
            'labels' => [
                'de' => ['wohnhaft in', 'Wohnort von'],
                'en' => ['resides in', 'residence of'],
            ],
            'temporal' => true,
        ],

        'hasSeatIn' => [
            'inverse' => 'isSeatOf',
            'pairs' => ['actor:place'],
            'labels' => [
                'de' => ['Sitz in', 'Sitz von'],
                'en' => ['seated in', 'seat of'],
            ],
            'temporal' => true,
        ],

        'isActiveIn' => [
            'inverse' => 'isPlaceOfActivityOf',
            'pairs' => ['actor:place'],
            'labels' => [
                'de' => ['tätig in', 'Wirkungsort von'],
                'en' => ['active in', 'place of activity of'],
            ],
            'temporal' => true,
        ],

        // places

        'isLocatedIn' => [
            'inverse' => 'contains',
            'hierarchical' => true,
            'pairs' => ['place:place'],
            'labels' => [
                'de' => ['liegt in', 'umfasst'],
                'en' => ['located in', 'contains'],
            ],
            'temporal' => true,
            'rico' => 'rico:isOrWasContainedBy',
        ],

        // records

        'isCopyOf' => [
            'inverse' => 'hasCopy',
            'pairs' => ['object:object'],
            'labels' => [
                'de' => ['Kopie von', 'hat Kopie'],
                'en' => ['copy of', 'has copy'],
            ],
            'rico' => 'rico:isCopyOf',
        ],

        'isTranscriptOf' => [
            'inverse' => 'hasTranscript',
            'pairs' => ['object:object'],
            'labels' => [
                'de' => ['Abschrift von', 'hat Abschrift'],
                'en' => ['transcript of', 'has transcript'],
            ],
        ],

        'isTranslationOf' => [
            'inverse' => 'hasTranslation',
            'pairs' => ['object:object'],
            'labels' => [
                'de' => ['Übersetzung von', 'hat Übersetzung'],
                'en' => ['translation of', 'has translation'],
            ],
        ],

        'isAttachmentOf' => [
            'inverse' => 'hasAttachment',
            'pairs' => ['object:object'],
            'labels' => [
                'de' => ['Beilage zu', 'hat Beilage'],
                'en' => ['attachment of', 'has attachment'],
            ],
        ],

        'hasSuccessorRecord' => [
            'inverse' => 'isSuccessorRecordOf',
            'pairs' => ['object:object'],
            'labels' => [
                'de' => ['Nachakte', 'Vorakte'],
                'en' => ['succeeding file', 'preceding file'],
            ],
        ],
    ],

    /*
     * Names found in existing data that mean one of the types above.
     */
    'aliases' => [
        'refersTo' => 'references',
        'isReferedBy' => 'isReferencedBy',
    ],
];
