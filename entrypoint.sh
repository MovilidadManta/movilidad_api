#!/bin/bash

# Agregar entrada al archivo /etc/hosts
if ! grep -q "10.230.1.23 sistematransitolocal.ant.gob.ec sistematransitolocal" /etc/hosts; then
    echo "10.230.1.23 sistematransitolocal.ant.gob.ec sistematransitolocal" >> /etc/hosts
    echo "Entrada añadida a /etc/hosts"
else
    echo "La entrada ya existe en /etc/hosts"
fi

# Ejecutar el comando principal del contenedor
exec "$@"