# Aztec - Advanced Layered CRUD Generator for Laravel

## Introduction

Aztec is a powerful Laravel package designed to automate the generation of a clean, layered architecture for your application modules. It goes beyond simple CRUD generation by implementing a robust Service-Repository pattern, ensuring your controllers remain thin and your business logic is well-encapsulated.

## Installation

You can install the package via composer:

```bash
composer require albaraa/aztec
```

## Features

- **Layered Architecture**: Generates Controller, Service, Repository (Interface & Implementation), API Resource, and Form Requests.
- **Module Support**: Designed to work intimately with modular structures (e.g., `Modules/Blog`).
- **Interactive Generation**: CLI prompts guide you through selecting relationships for resources, adding custom filters, and configuring relation syncing.
- **Smart Validation**: Automatically builds validation rules and translation keys (e.g., `validations.module.field.rule`) based on your model's attributes and casts.
- **Automatic Wiring**: Automatically binds repositories in your Module's ServiceProvider and appends routes to `web.php`.

## Usage

### Generating CRUD

The main entry point is the `aztec:make-crud` command. It requires the Module name and the Model name (which must already exist).

```bash
php artisan aztec:make-crud {Module} {Model}
```

**Example:**
To generate CRUD for a `Post` model inside the `Blog` module:

```bash
php artisan aztec:make-crud Blog Post
```

### The Generation Flow

When you run the command, Aztec performs the following steps:

1.  **Analysis**: It inspects your Model file to understand its fillable attributes, casts, and relationships.
2.  **Interactive Configuration**:
    - **Resources**: It asks which relationships should be included in the API Resource.
    - **Service Layer**: It prompts you to add custom filters for the listing endpoint (e.g., filter by status, type, etc.) and asks which relationships should be synced automatically during create/update operations.
3.  **File Generation**:
    - **Repository**: Generates `PostRepositoryInterface` and `EloquentPostRepository`. It checks `BlogServiceProvider` and binds the interface to the implementation automatically.
    - **Service**: Generates `PostService` containing business logic. It handles pagination, search (smartly guessing searchable fields), custom filtering, and transaction-wrapped CRUD operations.
    - **Requests**: Generates `PostStoreRequest` and `PostUpdateRequest` with rules inferred from your model. It also generates a `messages()` method returning code-based keys (e.g., `validations.blog.title.required`) for frontend localization.
    - **Controller**: Generates `PostController` which injects the `PostService`. It remains clean and acts only as a gateway.
    - **Resource**: Generates `PostResource` to format the JSON response.
    - **Routes**: Appends standard RESTful routes to `Modules/Blog/routes/web.php` with a clean group structure.

## Directory Structure Idea

Assuming a `Blog` module and `Post` model, Aztec creates/updates:

```
Modules/Blog/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── PostController.php
│   │   ├── Requests/
│   │   │   ├── PostStoreRequest.php
│   │   │   └── PostUpdateRequest.php
│   │   ├── Resources/
│   │   │   └── PostResource.php
│   ├── Repositories/
│   │   ├── Interfaces/
│   │   │   └── PostRepositoryInterface.php
│   │   ├── EloquentPostRepository.php
│   ├── Services/
│   │   └── PostService.php
│   └── Providers/
│       └── BlogServiceProvider.php [Updated for Binding]
└── routes/
    └── web.php [Updated with Routes]
```

## Customization

You can customize the available layers and generation behavior by publishing the config file (if available):

```bash
php artisan vendor:publish --tag=aztec-config
```

---

_Crafted for developers who value clean code and speed._
