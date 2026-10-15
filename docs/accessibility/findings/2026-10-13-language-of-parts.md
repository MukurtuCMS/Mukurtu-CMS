# 3.1.2 Language of Parts: re-evaluated against main

The ACR recorded 3.1.2 as `does-not-support` on 6 October 2026, on the
grounds that the language vocabulary had no code field, so there was no ISO
639 or BCP 47 value for a template to emit, and no template emitted one. PR
#2352 (issue #2328) added both on 9 October and is on `main`. This re-checks
the criterion against what the product now does.

**One level moves: 3.1.2 from `does-not-support` to `partially-supports`.**

## What was run

A site running `main` (`06603ba28`), with a language term and a dictionary
word created directly so the check did not depend on seeded content:

```php
$term = Term::create([
  'vid' => 'language',
  'name' => 'Hawaiian',
  'field_language_code' => 'haw',
]);
$word = Node::create([
  'type' => 'dictionary_word',
  'title' => 'aloha',
  'field_dictionary_word_language' => [$term->id()],
  'field_alternate_spelling' => ['alohā'],
]);
```

Note the field name: the word's language is `field_dictionary_word_language`.
`field_language` is a different field, on digital heritage, and the first
attempt at this fixture used it by mistake and produced no `lang` at all.
Worth knowing before anyone reads a blank result as a failure.

## What it emits

With `haw` on the term, the rendered word carries:

| Where | Markup |
|---|---|
| Page title | `<h1 lang="haw">` |
| Title field | `<div lang="haw" class="field field--name-title …">` |
| Alternate spellings | `<div lang="haw" class="field__item">`, both values |

`LanguageAttributeHooks` also marks additional entry terms and sample
sentences, including sample sentences nested under an entry rather than on
the word, which `findWord()` reaches by walking up the paragraph parents.

## And the negative control

With the Language code cleared on the same term, the page carries no `lang`
beyond the page language:

```
with a blank Language code, lang values are: ['en']
```

That matters: it is the documented behaviour ("when that is blank, nothing
is added"), so a site that has not filled in codes gets no markup rather
than wrong markup. It also means the clean result above is the mechanism
working, not a template emitting a constant.

## Why this is `partially-supports` and not `supports`

The mechanism exists and covers the densest in-language surface in the
product, but most content still cannot say what language it is in:

- **Digital heritage** titles, descriptions and transcriptions.
- **Taxonomy term names**, which on many sites are themselves in-language.
- **Collection and community descriptions.**

The digital heritage `field_language` cannot be pressed into service for
this. Its own description reads "Language(s) present in the digital heritage
item. This includes the textual metadata and any media assets", it is
unlimited cardinality, and it has `auto_create: TRUE`. So on an item
catalogued in English about Hawaiian-language material, reading a single
value as "this title is in Hawaiian" would mark English text as Hawaiian.
Wrong marking is worse for a screen reader user than no marking, so this
needs an explicit choice rather than an inference (#2354, #2357).

Within the dictionary, definition, pronunciation, translation and word
origin keep the page language. That is correct for most sites and wrong for
one writing a monolingual dictionary or using in-language spelling for
pronunciation, and it is currently a constant rather than a setting (#2353).
