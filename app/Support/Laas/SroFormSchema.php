<?php

namespace App\Support\Laas;

/**
 * The Application for Statutory Right of Occupancy, as the applicant fills it.
 *
 * Single source of truth for all four land types. The apply form renders from
 * it, the controller validates against it, and the status and staff screens
 * read submitted answers back through it — so a field cannot exist in one place
 * and be missing from another.
 *
 * Field keys are deliberately identical to the `oss_applications` column names
 * used by the staff modal (see _ossFieldMap in
 * public/js/lands-one-stop-shop/applications.js). A portal submission can
 * therefore be promoted into the live OSS table by column name, with no
 * translation layer to keep in step.
 *
 * Two departures from the staff modal, both because this form is filled by the
 * public rather than by a clerk:
 *
 *  - No file-number lookup, no Occupancy Permit / instrument section. Those are
 *    staff instruments and have no meaning before a file exists.
 *  - Address parts are free text, not selects. An applicant's home address may
 *    be anywhere in Nigeria, and the districts table holds ~1,800 rows —
 *    inlining it into these selects is exactly what makes the OSS applications
 *    screen unusable.
 */
class SroFormSchema
{
    public const TYPE_RESIDENTIAL  = 'residential';
    public const TYPE_COMMERCIAL   = 'commercial';
    public const TYPE_INDUSTRIAL   = 'industrial';
    public const TYPE_AGRICULTURAL = 'agricultural';

    public const TYPES = [
        self::TYPE_RESIDENTIAL  => ['label' => 'Residential Land',  'icon' => 'home'],
        self::TYPE_COMMERCIAL   => ['label' => 'Commercial Land',   'icon' => 'building-2'],
        self::TYPE_INDUSTRIAL   => ['label' => 'Industrial Land',   'icon' => 'factory'],
        self::TYPE_AGRICULTURAL => ['label' => 'Agricultural Land', 'icon' => 'wheat'],
    ];

    public const SEX = ['Male', 'Female'];
    public const MARITAL_STATUS = ['Single', 'Married', 'Divorced', 'Widowed'];
    public const YES_NO = ['Yes', 'No'];

    /** Number of prior-allocation rows the residential form offers. */
    public const PREV_ALLOCATION_ROWS = 3;

    public static function isValidType(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::TYPES);
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type]['label'] ?? (string) $type;
    }

    /**
     * Sections for one land type.
     *
     * Each field: key, label, type (text|textarea|select|number|address|
     * prev_allocations), plus optional required, help, options, prefix.
     */
    public static function sections(string $type): array
    {
        switch ($type) {
            case self::TYPE_RESIDENTIAL:
                return self::residential();
            case self::TYPE_COMMERCIAL:
                return self::commercial();
            case self::TYPE_INDUSTRIAL:
                return self::industrialLike('Industrial', 'ind_', 'nature_of_industrial', 'Nature of the industrial activity');
            case self::TYPE_AGRICULTURAL:
                return self::industrialLike('Agricultural', 'agr_', 'nature_of_agricultural', 'Nature of the agricultural activity');
        }

        return [];
    }

    /** Every field key for a type, flattened — address groups expanded. */
    public static function fieldKeys(string $type): array
    {
        $keys = [];

        foreach (self::sections($type) as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'address') {
                    foreach (array_keys(self::addressParts()) as $part) {
                        $keys[] = $field['prefix'] . $part;
                    }
                    continue;
                }

                if ($field['type'] === 'prev_allocations') {
                    continue; // Collected as rows, stored as JSON.
                }

                $keys[] = $field['key'];
            }
        }

        return $keys;
    }

    /** The parts every address block is built from. */
    public static function addressParts(): array
    {
        return [
            'plot'     => 'Plot Number',
            'street'   => 'Street Name',
            'district' => 'District',
            'lga'      => 'Local Government Area',
            'state'    => 'State',
        ];
    }

    /** Laravel validation rules for a submitted form of this type. */
    public static function rules(string $type): array
    {
        $rules = [];

        foreach (self::sections($type) as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'address') {
                    foreach (array_keys(self::addressParts()) as $part) {
                        $rules[$field['prefix'] . $part] = ['nullable', 'string', 'max:255'];
                    }
                    continue;
                }

                if ($field['type'] === 'prev_allocations') {
                    continue;
                }

                $rule = [($field['required'] ?? false) ? 'required' : 'nullable'];

                $rule[] = $field['type'] === 'number' ? 'numeric' : 'string';

                if (isset($field['options'])) {
                    $rule[] = 'in:' . implode(',', $field['options']);
                }

                $rule[] = $field['type'] === 'textarea' ? 'max:2000' : 'max:255';

                $rules[$field['key']] = $rule;
            }
        }

        return $rules;
    }

    /** Human label for a stored key, for reading answers back. */
    public static function labelFor(string $type, string $key): string
    {
        foreach (self::sections($type) as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'address') {
                    foreach (self::addressParts() as $part => $partLabel) {
                        if ($field['prefix'] . $part === $key) {
                            return $field['label'] . ' — ' . $partLabel;
                        }
                    }
                    continue;
                }

                if ($field['key'] === $key) {
                    return $field['label'];
                }
            }
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    // ---------------------------------------------------------------- types

    private static function residential(): array
    {
        return [
            [
                'title'  => '1. Applicant',
                'icon'   => 'user',
                'fields' => [
                    ['key' => 'applicant_name', 'label' => 'Applicant&rsquo;s full name', 'type' => 'text', 'required' => true],
                    ['key' => 'age', 'label' => '(A) Age', 'type' => 'number', 'help' => 'e.g. 35'],
                    ['key' => 'sex', 'label' => '(B) Sex', 'type' => 'select', 'options' => self::SEX],
                    ['key' => 'marital_status', 'label' => '(C) Marital status', 'type' => 'select', 'options' => self::MARITAL_STATUS],
                    ['key' => 'husband_name_address', 'label' => '(D) If a married woman, give the name and address of your husband', 'type' => 'textarea'],
                ],
            ],
            [
                'title'  => '2. Residential address',
                'icon'   => 'home',
                'note'   => 'A P.O. Box must not be given.',
                'fields' => [
                    ['key' => 'residential_address', 'label' => 'Residential address', 'type' => 'address', 'prefix' => 'res_addr_'],
                ],
            ],
            [
                'title'  => '3. Correspondence address',
                'icon'   => 'mail',
                'fields' => [
                    ['key' => 'correspondence_address', 'label' => 'Correspondence address', 'type' => 'address', 'prefix' => 'res_corr_'],
                ],
            ],
            [
                'title'  => '4. Contact',
                'icon'   => 'phone',
                'fields' => [
                    ['key' => 'email', 'label' => 'Email address', 'type' => 'text', 'help' => 'If any'],
                    ['key' => 'phone', 'label' => 'Phone number', 'type' => 'text', 'required' => true, 'help' => 'Updates on this application are sent here by SMS.'],
                ],
            ],
            [
                'title'  => '5. Business address',
                'icon'   => 'briefcase',
                'note'   => 'Only if different from your correspondence address above.',
                'fields' => [
                    ['key' => 'business_address', 'label' => 'Business address', 'type' => 'address', 'prefix' => 'res_biz_'],
                ],
            ],
            [
                'title'  => '6. Nationality and origin',
                'icon'   => 'flag',
                'fields' => [
                    ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text', 'help' => 'Indicate if naturalised'],
                    ['key' => 'state_of_origin', 'label' => '(A) State of origin', 'type' => 'text', 'help' => 'If Nigerian'],
                    ['key' => 'lga', 'label' => '(B) Local Government of origin', 'type' => 'text', 'help' => 'If Nigerian'],
                ],
            ],
            [
                'title'  => '7. Occupation',
                'icon'   => 'hard-hat',
                'fields' => [
                    ['key' => 'occupation', 'label' => 'Occupation', 'type' => 'text'],
                    ['key' => 'annual_income', 'label' => '(a) Annual income', 'type' => 'text', 'help' => 'e.g. 2,400,000'],
                ],
            ],
            [
                'title'  => '8. Previous allocation',
                'icon'   => 'history',
                'fields' => [
                    ['key' => 'prev_allocated', 'label' => 'Have you been allocated any residential plot before?', 'type' => 'select', 'options' => self::YES_NO],
                    ['key' => 'prev_allocation_details', 'label' => 'If yes, give the details', 'type' => 'prev_allocations'],
                ],
            ],
            self::remarksSection(),
        ];
    }

    private static function commercial(): array
    {
        return [
            [
                'title'  => '1. Applicant',
                'icon'   => 'user',
                'fields' => [
                    ['key' => 'applicant_name', 'label' => 'Name of applicant or company', 'type' => 'text', 'required' => true],
                ],
            ],
            [
                'title'  => '2. Nationality and background',
                'icon'   => 'flag',
                'fields' => [
                    ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text', 'help' => 'Indicate if naturalised'],
                    ['key' => 'state_of_origin', 'label' => '(a) State of origin', 'type' => 'text'],
                    ['key' => 'home_domicile', 'label' => '(b) Home domicile', 'type' => 'text'],
                    ['key' => 'occupation_or_business', 'label' => '(c) Occupation or business', 'type' => 'text'],
                    ['key' => 'nature_of_commerce', 'label' => '(d) Nature of commercial activity', 'type' => 'textarea'],
                    ['key' => 'annual_income', 'label' => '(e) Annual income', 'type' => 'text'],
                ],
            ],
            [
                'title'  => '3. Company details',
                'icon'   => 'building-2',
                'fields' => [
                    ['key' => 'company_registered_under', 'label' => '(a) Company is registered under', 'type' => 'text'],
                    ['key' => 'registration_particulars', 'label' => '(b) Registration particulars', 'type' => 'textarea'],
                    ['key' => 'business_location', 'label' => '(c) Business location', 'type' => 'address', 'prefix' => 'com_biz_'],
                    ['key' => 'correspondence_address', 'label' => '(d) Correspondence address', 'type' => 'address', 'prefix' => 'com_corr_'],
                    ['key' => 'annual_income_anticipation', 'label' => '(e) Annual or anticipated income', 'type' => 'text'],
                ],
            ],
            [
                'title'  => '4. Previous allocation',
                'icon'   => 'history',
                'fields' => [
                    ['key' => 'prev_allocated', 'label' => 'Have you ever been allocated commercial land before?', 'type' => 'select', 'options' => self::YES_NO],
                    ['key' => 'prev_allocation_details', 'label' => '(a) If yes, state when and where, and the Certificate of Occupancy number', 'type' => 'textarea'],
                    ['key' => 'prev_land_purpose', 'label' => '(b) Purpose for which the land above is being used', 'type' => 'textarea'],
                ],
            ],
            [
                'title'  => '5. Purpose',
                'icon'   => 'target',
                'fields' => [
                    ['key' => 'purpose', 'label' => 'Purpose for which the land applied for is required', 'type' => 'textarea'],
                    ['key' => 'intended_activities', 'label' => '6. What type of activities do you intend to undertake?', 'type' => 'textarea'],
                ],
            ],
            self::contactSection(),
            self::remarksSection(),
        ];
    }

    /**
     * Industrial and Agricultural are the same paper form with one word and one
     * field changed, so they share a builder rather than being copied.
     */
    private static function industrialLike(string $word, string $prefix, string $natureKey, string $natureLabel): array
    {
        $lower = strtolower($word);

        return [
            [
                'title'  => '1. Applicant',
                'icon'   => 'user',
                'fields' => [
                    ['key' => 'applicant_name', 'label' => 'Name of applicant or company', 'type' => 'text', 'required' => true],
                ],
            ],
            [
                'title'  => '2. Nationality',
                'icon'   => 'flag',
                'fields' => [
                    ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text', 'help' => 'Indicate if naturalised'],
                    ['key' => 'state_of_origin', 'label' => '(a) State of origin', 'type' => 'text'],
                    ['key' => 'home_domicile', 'label' => '(b) Home domicile', 'type' => 'text'],
                ],
            ],
            [
                'title'  => '3. Occupation or business',
                'icon'   => 'hard-hat',
                'fields' => [
                    ['key' => 'occupation_or_business', 'label' => '3. Occupation or business', 'type' => 'text'],
                    ['key' => 'nature_of_occupation', 'label' => '4. Nature of occupation or business', 'type' => 'textarea'],
                ],
            ],
            [
                'title'  => '5. Registration details',
                'icon'   => 'building-2',
                'fields' => [
                    ['key' => 'company_registered_under', 'label' => '(A) Applicant or company is registered under', 'type' => 'text'],
                    ['key' => 'registration_particulars', 'label' => '(B) Registration particulars', 'type' => 'textarea'],
                    ['key' => 'business_location', 'label' => '(C) Business location', 'type' => 'address', 'prefix' => $prefix . 'biz_'],
                    ['key' => 'correspondence_address', 'label' => '(D) Correspondence address', 'type' => 'address', 'prefix' => $prefix . 'corr_'],
                ],
            ],
            [
                'title'  => '6. Income and employees',
                'icon'   => 'coins',
                'fields' => [
                    ['key' => 'annual_income_turnover', 'label' => '6. Annual income or turnover', 'type' => 'text'],
                    ['key' => 'number_of_employees', 'label' => '7. Number of employees', 'type' => 'number'],
                ],
            ],
            [
                'title'  => '8. Previous allocation',
                'icon'   => 'history',
                'fields' => [
                    ['key' => 'prev_allocated', 'label' => 'Have you ever been allocated ' . $lower . ' land before?', 'type' => 'select', 'options' => self::YES_NO],
                    ['key' => 'prev_allocation_details', 'label' => 'If yes, give the date of the allocation and the Certificate of Occupancy number', 'type' => 'textarea'],
                ],
            ],
            [
                'title'  => '9. Purpose',
                'icon'   => 'target',
                'fields' => [
                    ['key' => 'purpose', 'label' => 'Purpose for which the land applied for is acquired', 'type' => 'textarea'],
                    ['key' => $natureKey, 'label' => $natureLabel, 'type' => 'textarea'],
                    ['key' => 'waste_disposal_requirements', 'label' => 'Any special requirement needed for waste disposal', 'type' => 'textarea'],
                ],
            ],
            self::contactSection(),
            self::remarksSection(),
        ];
    }

    /**
     * Contact details. Not numbered on the commercial, industrial and
     * agricultural paper forms — which assume a clerk already holds them — but
     * the portal cannot text an applicant it has no number for.
     */
    private static function contactSection(): array
    {
        return [
            'title'  => 'Contact',
            'icon'   => 'phone',
            'fields' => [
                ['key' => 'email', 'label' => 'Email address', 'type' => 'text', 'help' => 'If any'],
                ['key' => 'phone', 'label' => 'Phone number', 'type' => 'text', 'required' => true, 'help' => 'Updates on this application are sent here by SMS.'],
            ],
        ];
    }

    private static function remarksSection(): array
    {
        return [
            'title'  => 'Anything else',
            'icon'   => 'message-square',
            'fields' => [
                ['key' => 'remarks', 'label' => 'Remarks or notes', 'type' => 'textarea'],
            ],
        ];
    }
}
