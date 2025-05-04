FROM php:8.2-apache

COPY . /var/www/html/

# # Instalează dependențele necesare pentru Oracle
# RUN apt-get update && apt-get install -y \
#     libaio1 \
#     unzip \
#     wget \
#     build-essential \
#     libcurl4-openssl-dev \
#     libssl-dev \
#     libaio-dev \
#     gcc \
#     make

# # Descarcă și instalează Oracle Instant Client
# RUN wget https://download.oracle.com/otn_software/linux/instantclient/213000/oracle-instantclient-basic-21.3.0.0.0-1.x86_64.rpm && \
#     wget https://download.oracle.com/otn_software/linux/instantclient/213000/oracle-instantclient-sqlplus-21.3.0.0.0-1.x86_64.rpm && \
#     alien -i oracle-instantclient-basic-21.3.0.0.0-1.x86_64.rpm && \
#     alien -i oracle-instantclient-sqlplus-21.3.0.0.0-1.x86_64.rpm

# # Instalează PHP OCI8
# RUN pecl install oci8-2.2.0 && \
#     echo "extension=oci8.so" >> /usr/local/etc/php/conf.d/oci8.ini

RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/g' /etc/apache2/sites-available/000-default.conf \
    && echo "DirectoryIndex WoW.html" >> /etc/apache2/apache2.conf

EXPOSE 8080

CMD ["apache2-foreground"]