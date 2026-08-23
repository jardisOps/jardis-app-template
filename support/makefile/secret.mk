# Taken from jardissupport/secret (support/makefile/secret.mk). One deviation:
# messages translated to English (repo rule: all artifacts in English). The
# PHP invocation already matches this repo's container form (phpcli).
<---secret-key--->: ## -----------------------------------------------------------------------
KEY_FILE ?= support/secret.key

generate-key: ## Generate a new AES/Sodium encryption key (base64-encoded)
	@$(DOCKER_COMPOSE) run --rm --no-deps phpcli php -r 'echo base64_encode(random_bytes(32)) . PHP_EOL;'

generate-key-file: ## Generate key and save to $(KEY_FILE)
	@if [ -f $(KEY_FILE) ]; then echo "$(KEY_FILE) already exists — delete or rename it first."; exit 1; fi
	@$(DOCKER_COMPOSE) run --rm --no-deps phpcli php -r 'echo base64_encode(random_bytes(32));' > $(KEY_FILE)
	@chmod 600 $(KEY_FILE)
	@echo "Key saved to $(KEY_FILE) (chmod 600)"
	@echo "IMPORTANT: do NOT commit this file to git!"

encrypt: ## Encrypt a value with AES — make encrypt VALUE="plaintext"
	@if [ ! -f $(KEY_FILE) ]; then echo -e "\033[0;31m$(KEY_FILE) not found — run make generate-key-file first.\033[0m"; exit 1; fi
	@echo ""
	@echo -e "\033[0;32m  ✔ Encrypted (AES-256-GCM):\033[0m"
	@echo ""
	@echo -n "  "
	@$(DOCKER_COMPOSE) run --rm --no-deps phpcli php -r '\
		$$key = base64_decode(trim(file_get_contents("$(KEY_FILE)"))); \
		require "vendor/autoload.php"; \
		echo "\033[1;33msecret(" . \JardisSupport\Secret\Resolver\AesSecretResolver::encrypt("$(VALUE)", $$key) . ")\033[0m" . PHP_EOL;'
	@echo ""

encrypt-sodium: ## Encrypt a value with Sodium — make encrypt-sodium VALUE="plaintext"
	@if [ ! -f $(KEY_FILE) ]; then echo -e "\033[0;31m$(KEY_FILE) not found — run make generate-key-file first.\033[0m"; exit 1; fi
	@echo ""
	@echo -e "\033[0;32m  ✔ Encrypted (Sodium XSalsa20-Poly1305):\033[0m"
	@echo ""
	@echo -n "  "
	@$(DOCKER_COMPOSE) run --rm --no-deps phpcli php -r '\
		$$key = base64_decode(trim(file_get_contents("$(KEY_FILE)"))); \
		require "vendor/autoload.php"; \
		echo "\033[1;33msecret(sodium:" . \JardisSupport\Secret\Resolver\SodiumSecretResolver::encrypt("$(VALUE)", $$key) . ")\033[0m" . PHP_EOL;'
	@echo ""
.PHONY: generate-key generate-key-file encrypt encrypt-sodium
