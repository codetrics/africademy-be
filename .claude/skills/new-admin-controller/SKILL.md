---
name: new-admin-controller
description: Scaffold an admin dashboard controller for this Symfony project. Use when the user asks to create an admin page, admin view, or admin UI controller. Admin controllers render Twig templates, are protected by ROLE_ADMIN, and live under /admin/.
disable-model-invocation: false
argument-hint: [ControllerName]
allowed-tools: Read Write Glob Grep Bash(ls *)
---

Create a new admin controller named `Admin$ARGUMENTSController`.

## Live context

Existing admin controllers:
```!
ls src/Controller/ | grep ^Admin
```

Related Twig templates directory (to see what already exists):
```!
ls templates/admin/ 2>/dev/null
```

## Instructions

1. **Check for duplicates** — if `src/Controller/Admin$ARGUMENTSController.php` already exists, stop and tell the user.
2. **Read a similar existing admin controller** (e.g. `AdminProductsController.php` or `AdminOrdersController.php`) to match the project style.
3. **File location:** `src/Controller/Admin$ARGUMENTSController.php`
4. Also create the Twig template directory: `templates/admin/<resource>/` if it doesn't exist.

## Checklist

- [ ] Class name starts with `Admin` and ends with `Controller`
- [ ] Extends `AbstractController`
- [ ] **No constructor** — security is enforced by the `main` firewall `access_control`, not by `#[IsGranted]`
- [ ] Returns only Twig-rendered `Response` — **no JSON**
- [ ] Dependencies injected as action-method parameters (autowired), not via constructor
- [ ] Pass only scalar data to templates — no raw Doctrine collections
- [ ] Flash messages: `$this->addFlash('success', '...')` / `$this->addFlash('error', '...')`
- [ ] POST handlers redirect after success: `$this->redirectToRoute('...')` (PRG pattern)
- [ ] Form handling: `$form->handleRequest($request)` → `isSubmitted() && isValid()`
- [ ] Route prefix: `/admin/$arguments`
- [ ] Route name prefix: `app_admin_$arguments_`

## Template (index + new with form)

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\{Name}Type;
use App\Service\{Name}Service;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class Admin{Name}Controller extends AbstractController
{
    #[Route('/admin/{resource}', name: 'app_admin_{resource}_index', methods: ['GET'])]
    public function index({Name}Service ${name}Service): Response
    {
        return $this->render('admin/{resource}/index.html.twig', [
            '{name}s' => ${name}Service->findAll(),
        ]);
    }

    #[Route('/admin/{resource}/new', name: 'app_admin_{resource}_new', methods: ['GET', 'POST'])]
    public function new(Request $request, {Name}Service ${name}Service): Response
    {
        $form = $this->createForm({Name}Type::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            ${name}Service->create($form->getData());
            $this->addFlash('success', '{Name} created successfully.');
            return $this->redirectToRoute('app_admin_{resource}_index');
        }

        return $this->render('admin/{resource}/new.html.twig', [
            'form' => $form,
        ]);
    }
}
```

Replace `{Name}` with PascalCase of `$ARGUMENTS` and `{name}` / `{resource}` with the snake_case/kebab-case form.
