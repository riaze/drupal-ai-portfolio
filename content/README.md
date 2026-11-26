# Site content (Markdown)

Everything visitors read lives here as Markdown, one file per page and per
language. `make import-content` loads it into Drupal; running it again only
applies files that changed.

```
content/
├── _templates/        Copy a template to start a new file (never imported)
├── images/            Images referenced by "image:" (shared by FR and EN)
├── fr/                French files (default language)
│   ├── pages/         Home, about, legal notice, privacy policy
│   ├── services/
│   ├── case-studies/
│   ├── articles/
│   ├── faq/
│   └── taxonomy/faq_category.yml
└── en/                English files, same structure
```

Folder names are only for you: the `type` in the front matter decides the
content type. Files whose name starts with `_`, and `README.md`, are ignored.

## Translations

A French file and an English file with the **same `id`** are the two
translations of one page. The French file is the source: it creates the page,
and settings shared by both languages (menu, technologies, links, date,
category, image file) are read from it. Each language has its own title,
text, summary, URL (`path`), tags and image alternative text.

## Publishing safely

- Every template starts with `status: draft`: drafts are never visible to
  visitors.
- Text between double brackets, like `[[Your name]]`, is a placeholder. The
  importer **refuses to publish** a file that still contains one.
- Articles and case studies follow the editorial workflow: `draft`, `review`
  or `published`.
- Guidance written as HTML comments (`<!-- ... -->`) is removed on import.

## Front matter reference

Common keys:

| Key | Required | Description |
| --- | --- | --- |
| `id` | yes | Stable key shared by the FR and EN files: lowercase letters, digits, dashes. Never change it after the first import. |
| `type` | yes | `page`, `service`, `case_study`, `article` or `faq` |
| `title` | yes | Page title (`<h1>`); for `faq`, the question |
| `status` | no | `draft` (default) or `published`; `review` for articles and case studies |
| `path` | no | URL in this language, e.g. `/a-propos`. Generated from the title when omitted. |
| `menu` | no | `main` or `footer` (read from the French file) |
| `menu_weight` | no | Position in the menu; lower comes first |
| `menu_title` | no | Shorter label for the menu |

Per type:

| Type | Keys | Body |
| --- | --- | --- |
| `page` | – | The page text. The page with `id: home` is the front page. |
| `service` | `summary` (required to publish), `order`, `cta_label` + `cta_url` | Description of the service |
| `case_study` | `summary` (required to publish), `stack` (list), `repo_url`, `demo_url` (https), `image` + `image_alt` | Three sections: `<!-- field: problem -->`, `<!-- field: solution -->`, `<!-- field: results -->` |
| `article` | `published_date` (YYYY-MM-DD), `tags` (list), `summary` (optional), `image` + `image_alt` | The article |
| `faq` | `category` (a key of `taxonomy/faq_category.yml`) | The answer |

Writing Markdown: start sections with `##` (the title is the only `#`
heading). HTML tags are shown as text, not rendered.

An article `summary` can stay empty: it is meant to be written later, or
suggested by the AI assistant and approved by a human (phase 2). The importer
never overwrites a summary with an empty value.

## Commands

```bash
make import-content                       # import new and changed files
cd drupal && ddev drush pic --dry-run     # validate without saving
cd drupal && ddev drush pic --force       # re-apply every file
```

Edits made in the Drupal admin are kept until the corresponding file changes.
If you edit a page in Drupal, copy the change back into its file, or the next
change to the file will replace it.
