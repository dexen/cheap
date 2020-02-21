# dexen/cheap
Git implementation in PHP 7.4+. Two libraries and a set of CLI tools.

## Project status

Early stage of development. Expect the OOP part of API to be unstable before version 1.

## Planned APIs and tools

1. drop-in replacement for Git's CLI tools, ex: `git-php checkout my-branch`
1. a high level, text-centric PHP API for typical Git operations, provided through class `Textual`
1. an Object-Oriented PHP API for all operations, internals, etc., anchored at class `Repository`

The CLI tools are meant to be drop-in replacement for Git's original CLI toolkit.
We aim for semantic compatibility and similar input & output format.

The `Textual` PHP API is meant to be minimalistic, mostly stable (as it is modeled after the CLI tools),
and easy to start working with. Its output is meant primarily for human consumption,
with some concessions for piping to other tools, just like Git CLI tools' output.
This API underpins the CLI tools.

The `Repository` OO PHP API for advanced use cases, providing full and granular r/w access
to all aspects of the repository, primarily with object semantics.
This API underpins the `Textual` API.

## Project plans

Following is the preliminary plan for implementation. The first goal is to become self-hosting.
The functonalities for the first goal are to be implemented in minimal way.

1. Goal 1: become self-hosting; implement minimalistic versions of selected core features
	1. `git-config` support (r/o)
	1. `git-branch` support (r/o)
	1. `git-fsck` support
	1. `git-log` support
	1. `git-diff` support
	1. `git-tag` support
	1. `git-add` support
	1. `git-commit` and `git-checkout` support
	1. `git-push` / `git-pull` support
	1. `git-rebase` support. This should complete goal of being self-hosting.
1. Future goal: further features as needed, eg. submodules, hooks, etc.
1. Future goal: ability to serve (r/w) Git repositories for pushes & pulls
from shared web hosting servers.
