PHPFILES ?= $(wildcard $(CURDIR)/*.php)

# Executables
PHP7 := php7.3
PHP8 := php8.3

test:
	phpcs -s --standard=$(CURDIR)/phpcs.ruleset.xml $(PHPFILES)

test-error:
	phpcs -n -s --standard=$(CURDIR)/phpcs.ruleset.xml $(PHPFILES)

info:
	@echo "$(PHPFILES)"

lint: lint8

lint7:
	$(PHP7) --version
	for f in $(PHPFILES); do $(PHP7) -l "$$f"; done

lint8:
	$(PHP8) --version
	$(PHP8) -l $(PHPFILES)    # php8 takes multiple files as arguments

functionlist:
	cat $(PHPFILES) | egrep -o '(function |->)?[A-Za-z_]+\(' | grep -v '^function' | grep -v '^->' | sort | uniq

.PHONY: test info lint7 lint8
