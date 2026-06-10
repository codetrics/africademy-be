---
name: new-form
description: Scaffold a Symfony form type for admin use. Use when the user asks to create a form, form type, or admin form for creating or editing an entity. Forms in this project are used only in admin controllers — API controllers parse JSON directly.
disable-model-invocation: false
argument-hint: [FormName] [EntityClassName]
allowed-tools: Read Write Glob Grep Bash(ls *)
---

Create a new Symfony form type. Arguments: `$ARGUMENTS`
(Usage: `/new-form ExampleType Example` — form name first, entity class name second)

Form name: `$ARGUMENTS[0]`
Entity class (optional): `$ARGUMENTS[1]`

## Live context

Existing form types:
```!
ls src/Form/
```

Entity class to bind (if provided):
```!
ls "src/Entity/$ARGUMENTS[1].php" 2>/dev/null && echo "Entity found" || echo "No entity argument provided or entity not found"
```

## Instructions

1. **Check for duplicates** — if `src/Form/$ARGUMENTS[0].php` already exists, stop.
2. **Read a similar form** (e.g. `ContactType.php`) to match the project's field and option style.
3. **File location:** `src/Form/$ARGUMENTS[0].php`

## Checklist

- [ ] `declare(strict_types=1)` at the top
- [ ] Extend `AbstractType`
- [ ] `buildForm()` adds fields using Symfony form type classes (`TextType`, `EmailType`, `ChoiceType`, etc.)
- [ ] `configureOptions()` sets `data_class` to the bound entity (if any)
- [ ] Validation constraints on fields use **named parameters**: `new NotBlank(message: '...')` — never deprecated array-style
- [ ] Constructor: property-promoted `private readonly` dependencies only when needed (e.g. to populate choice options from a service)

## Template

```php
<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\{Entity};
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class {FormName} extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label'       => 'Name',
                'required'    => true,
                'constraints' => [
                    new NotBlank(message: 'Name is required.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label'    => 'Email',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => {Entity}::class,
        ]);
    }
}
```

Replace `{FormName}` with `$ARGUMENTS[0]` and `{Entity}` with `$ARGUMENTS[1]` (or remove `data_class` if not bound to an entity).
Add and remove fields to match the actual requirements.
