### Variables

# Applications
COMPOSER ?= /usr/bin/env composer
DEPENDENCIES ?= lastest
PHP ?= /usr/bin/env php

### Helpers
all: clean depend

.PHONY: all

### Dependencies
depend:
ifeq ($(DEPENDENCIES), lowest)
	${COMPOSER} update --prefer-lowest --prefer-dist --no-interaction;
else
	${COMPOSER} update --prefer-dist --no-interaction;
endif

.PHONY: depend

### QA
qa: lint phpstan phpcs audit
qa-offline: lint phpstan phpcs

lint:
	find ./src -name "*.php" -exec ${PHP} -l {} \; | grep "Parse error" > /dev/null && exit 1 || exit 0
	find ./infrastructures -name "*.php" -exec ${PHP} -l {} \; | grep "Parse error" > /dev/null && exit 1 || exit 0

phpstan:
	${PHP} -d memory_limit=256M vendor/bin/phpstan analyse

phpcs:
	${PHP} vendor/bin/phpcs --standard=PSR12 --extensions=php src/ infrastructures/

audit:
	${COMPOSER} audit

.PHONY: qa qa-offline lint phpstan phpcs audit

### Testing
# Isolated tests run in dedicated PHP processes, which do not inherit the `-d` options of the command line: when Xdebug
# is not already enabled in the PHP configuration, it is loaded from an ini file, also scanned by these processes.
ifeq ($(shell ${PHP} -r 'echo (int) extension_loaded("xdebug");'),1)
PHP_WITH_XDEBUG = XDEBUG_MODE=coverage ${PHP}
else
PHP_WITH_XDEBUG = XDEBUG_MODE=coverage PHP_INI_SCAN_DIR=":$(CURDIR)/tests/support/xdebug" ${PHP}
endif

test:
	${PHP_WITH_XDEBUG} -dxdebug.mode=coverage vendor/bin/phpunit -c phpunit.xml --colors --coverage-text
	${PHP} vendor/bin/behat
	rm -rf tests/var/cache/

.PHONY: test

### System.css x Bootstrap theme
# Copies the stylesheet, the fonts and the license of the theme from a clone of its repository
# (https://github.com/TeknooSoftware/system.css-x-bootstrap) into `assets/dist/system-theme/`.
SYSTEM_THEME_DIR ?= ../../ui/system.css-x-bootstrap
SYSTEM_THEME_FONTS = ChicagoFLF monaco ChiKareGo2

system-theme:
	test -f "${SYSTEM_THEME_DIR}/css/bootstrap-system-theme.css"
	mkdir -p assets/dist/system-theme/css assets/dist/system-theme/fonts
	cp "${SYSTEM_THEME_DIR}/css/bootstrap-system-theme.css" "${SYSTEM_THEME_DIR}/css/bootstrap-system-theme.min.css" assets/dist/system-theme/css/
	for font in ${SYSTEM_THEME_FONTS}; do \
		cp "${SYSTEM_THEME_DIR}/fonts/$$font.woff" "${SYSTEM_THEME_DIR}/fonts/$$font.woff2" assets/dist/system-theme/fonts/ || exit 1; \
	done
	cp "${SYSTEM_THEME_DIR}/LICENSE" assets/dist/system-theme/LICENSE

.PHONY: system-theme

### Cleaning
clean:
	rm -rf vendor
	rm -rf tests/var/cache/*

.PHONY: clean
