# Elections plugin for glFusion - Changelog

## v0.2.0
Release TBD
  * Option to display answer remarks on the election form.
  * Fix date selectors for glFusion 1.x/2.x differences.
  * Add option to display answer comments on election forms.

## v0.1.2
Release 2021-11-30
  * Implement phpGettext class for better locale handling on Windows.
  * Enable vote editing.
  * Add "alphabetically" as an option to sort answers when displayed.

## v0.1.1
Release 2021-04-06
  * Add missing Voter::anonymize() function to handle user deletion.

## v0.1.0
Release 2021-03-28

These reflect the changes relative to the existing Polls plugin on which the
Elections plugin is based.

  * Refactor into object classes.
  * Fix updating creation date whenever a topic is saved.
  * Remove UNIX-style permissions, use group for access to voting and results.
  * Add opening and closing date/time fields to control the voting window.
  * Get vote and question counts directly from the related tables for accuracy.
  * After closing, highlight winning selection(s) in results display.
  * Voters can be allowed to verify their votes by entering a key.
  * Results are shown ordered by number of votes received.
  * Elections can highlight the winner, or simply show the relative scores.
  * Questions and/or answer options can be shown in random order.
  * Only open elections appear in the block.
