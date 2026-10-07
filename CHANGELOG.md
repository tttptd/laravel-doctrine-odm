# Changelog

## 0.1.5 (не выпущен)

- Добавлен публичный `SchemaOwnershipRegistry` для регистрации документов
  и физических коллекций, управляемых явной командой владельца.
- Общие create/update/drop сохраняют зарегистрированные схемы, включая индексы,
  validators и search indexes. Адресный вызов и удаление защищённой базы
  отказывают до записи; конфликт владельцев проверяется предварительно.
- Защита учитывает configured names, отдельные базы и альтернативные mapping;
  обнаружение документов, runtime и генерация служебных классов сохраняются.
- Добавлены проверки поведения на изолированной MongoDB и ADR о границе CLI.

## 0.1.4

- Добавлен интеграционный тест сборочной команды для обычных и встроенных
  package-owned ODM-моделей.
- Подтверждена загрузка готовых гидраторов при `AUTOGENERATE_NEVER` из каталога
  без права записи; production-код не изменён.
- Уточнена ответственность package provider и host при сборочной генерации.

## 0.1.3

- Добавлена настройка `mongodb.paths.hydrators.auto_generate` для управления
  режимом генерации гидраторов Doctrine ODM.

## 0.1.2

- Forward supported Artisan command options to the wrapped native Doctrine command.
- Dropped Laravel 11 support from Composer constraints and CI.

## 0.1.1

- Added `DocumentPathRegistry` for package-owned Doctrine ODM document paths.

## 0.1.0

- Initial public release.
