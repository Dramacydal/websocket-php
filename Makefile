install: composer.phar
	./composer.phar install

update: composer.phar
	./composer.phar self-update
	./composer.phar update

composer.phar:
	curl -s http://getcomposer.org/installer | php

composer.lock: composer.phar
	./composer.phar --no-interaction install

build:
	mkdir build

clean:
	rm composer.phar
	rm -r vendor
	rm -r build
