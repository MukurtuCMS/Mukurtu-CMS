# Description
This module provides everything related to the dictionary in Mukurtu. This includes bundle classes for:
  * Content (node)
    * Dictionary words (`DictionaryWord`)
    * Word Lists (`WordList`)
  * Paragraphs
    * Dictionary Word Entries (`DictionaryWordEntry`)
    * Sample Sentences (`SampleSentence`)

as well as the dictionary page itself at route `mukurtu_dictionary.dictionary_page`.

## Dictionary Page
The dictionary is essentially a dictionary oriented analog to the "browse" page. It provides a discovery interface specifically for dictionary words and word lists.

## Glossary
Dictionary Words have a `field_glossary_entry` field. The purpose of this field is to allow the user to specify which character or characters should be used to represent this word in the glossary facet. There is a compute step in `preSave` that defaults this field to the first character of the title if not set explicitly.


