FROM php:8.0-cli

COPY builds/coding /usr/local/bin/

ENTRYPOINT ["coding"]
CMD ["list"]
