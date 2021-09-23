# Elections plugin for glFusion - Changelog

## v0.1.2
Release TBD

  * Enable vote editing.

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
