SHELL := bash
.SHELLFLAGS := -eu -o pipefail -c
MAKEFLAGS += --warn-undefined-variables
DOCKER_COMPOSE := docker compose

.DEFAULT_GOAL := help

# `.env` is not versioned. This rule is what makes a fresh clone work: GNU
# make remakes a missing include, then restarts itself with the copy in place
# — no bootstrap step, no `--env-file`, and `docker compose` reads the same
# file natively. There is no overlay layer to include (one file, no
# overlays).
#
# Deliberately WITHOUT `.env.example` as a prerequisite: a target with no
# prerequisites counts as up to date once it exists, so the copy happens
# exactly once, when the file is missing. With the prerequisite, every pull
# that touches .env.example would silently overwrite the developer's live
# configuration (measured). New keys from an update are merged by hand.
include .env

.env:
	cp .env.example $@

include support/makefile/stack.mk
include support/makefile/composer.mk
include support/makefile/qa-stack.mk
include support/makefile/docker.mk
include support/makefile/secret.mk
include support/makefile/ssh.mk
include support/makefile/hooks.mk

help:
	@echo -e "\033[0;32m Usage: make [target] "
	@echo
	@echo -e "\033[1m targets:\033[0m"
	@egrep '^(.+):*\ ##\ (.+)' ${MAKEFILE_LIST} | sed 's|^[^:]*:||' | column -t -c 2 -s ':#'
.PHONY: help

<---env-------->: ## -----------------------------------------------------------------------
env-parity-check: ## Check .env.example ENV-key parity against the Kernel example, blockwise (see README)
	bin/sync-env-from-kernel.sh --check
.PHONY: env-parity-check
