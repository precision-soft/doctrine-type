# Doctrine type

You may fork and modify it as you wish.

Any suggestions are welcomed.

## Usage

- extend `\PrecisionSoft\Doctrine\Type\Contract\AbstractEnumType` for enums.
- extend `\PrecisionSoft\Doctrine\Type\Contract\AbstractSetType` for sets.

### Symfony

```yaml
doctrine:
    dbal:
        default_connection: master
        connections:
            master:
                url: '%env(resolve:DATABASE_URL)%'
                server_version: '%env(MYSQL_SERVER_VERSION)%'
                mapping_types:
                    enum: string
                    set: string
        types:
            datetime: \PrecisionSoft\Doctrine\Type\DateTimeType
            AcmeEnum: \App\Doctrine\Type\AcmeEnum
            AcmeSet: \App\Doctrine\Type\AcmeSet
```

## Todo

## Dev

```shell
git clone git@gitlab.com:precision-soft-open-source/doctrine/type.git
cd type

./dc build && ./dc up -d
```
