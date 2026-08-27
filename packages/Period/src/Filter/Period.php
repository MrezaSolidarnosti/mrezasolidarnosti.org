<?php

namespace Solidarity\Period\Filter;

use Skeletor\Core\Filter\Str;

use Doctrine\ORM\EntityManagerInterface;
use Skeletor\Core\Filter\FilterInterface;
use Skeletor\Core\Security\Csrf;
use Skeletor\Core\Validator\ValidatorException;
class Period implements FilterInterface
{

//    public function __construct(private \Solidarity\Period\Validator\Period $validator)
    public function __construct()
    {
    }

    public function getErrors()
    {
        return [];
//        return $this->validator->getMessages();
    }

    public function filter($postData): array
    {
        $alnum = static fn ($v) => Str::alnum((string) $v, true);

        // Everything is cast to the type its entity property declares. Until this filter was
        // wired into the service it never ran, and the raw POST reached AbstractFactory, which
        // assigns values verbatim — PHP's non-strict coercion covered '2026' -> int and
        // '1' -> bool, so the gap only showed on a blank field, where '' is not a numeric
        // string and the assignment is a TypeError.
        //
        // month and year are ?int, so blank is null rather than 0: they identify a round, and
        // a period of month 0 is not the same statement as a period with no month.
        // month and year are ?int PROPERTIES but NOT NULL COLUMNS — their #[ORM\Column] carries
        // no `nullable: true`. So null must never reach them, exactly as the note on maxAmount
        // below records for that field. The month select is required and defaults to nothing,
        // so in practice a value always arrives; a stray blank lands as 0 and renders as a
        // literal "0" in the table via getHrMonth(), which is visible rather than fatal.
        $data = [
            'id' => (isset($postData['id'])) ? (int) ($postData['id']) : null,
            'month' => (int) ($postData['month'] ?? 0),
            'year' => (int) ($postData['year'] ?? 0),
            'type' => $postData['type'] ?? null,
            'active' => (bool) ($postData['active'] ?? false),
            'project' => $postData['project'] ?? null,
            'processing' => (bool) ($postData['processing'] ?? false),
            // Was missing, so the "Max iznos" input on the period form was posted and then
            // silently dropped on every save. Blank means 0, not null: the column is NOT
            // NULL, and 0 is already how "no per-period override" is spelled - MigrateLegacy
            // writes it for every legacy period, and Beneficiary\Validator reads any value
            // <= 0 as "fall back to the global limit". Writing null here was a 500 on save.
            'maxAmount' => (int) ($postData['maxAmount'] ?? 0),
            // ?? null because this filter now runs on every save: a POST without the token
            // would otherwise be an undefined-key warning here rather than a clean failure
            // somewhere that can report it. The value is unset again below — this filter reads
            // the token, it does not validate it.
            Csrf::TOKEN_NAME => $postData[Csrf::TOKEN_NAME] ?? null,
        ];
//        if (!$this->validator->isValid($data)) {
//            throw new ValidatorException();
//        }
        unset($data[Csrf::TOKEN_NAME]);

        return $data;
    }

}