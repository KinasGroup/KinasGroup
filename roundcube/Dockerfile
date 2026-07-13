FROM roundcube/roundcubemail:latest

# Download and extract Roundcube
RUN apt-get update && apt-get install -y curl && \
    curl -L https://github.com/roundcube/roundcubemail/releases/download/1.6.7/roundcubemail-1.6.7-complete.tar.gz -o /tmp/roundcube.tar.gz && \
    tar -xzf /tmp/roundcube.tar.gz -C /var/www/html/ && \
    mv /var/www/html/roundcubemail-1.6.7/* /var/www/html/ && \
    rm -rf /var/www/html/roundcubemail-1.6.7 /tmp/roundcube.tar.gz && \
    chown -R www-data:www-data /var/www/html

# Copy start script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Create config directory and file
RUN mkdir -p /var/www/html/config /var/www/html/logs /var/www/html/temp

# Create config file
RUN echo '<?php' > /var/www/html/config/config.inc.php && \
    echo '$config = array();' >> /var/www/html/config/config.inc.php && \
    echo '$config["db_dsnw"] = "mysql://root:xFpLpHtZgWqiNPVGBGJGYPexiLCznkft@mysql-geov.railway.internal/railway";' >> /var/www/html/config/config.inc.php && \
    echo '$config["default_host"] = "imaps://imap.gmail.com:993";' >> /var/www/html/config/config.inc.php && \
    echo '$config["smtp_server"] = "tls://smtp.resend.com";' >> /var/www/html/config/config.inc.php && \
    echo '$config["smtp_port"] = 587;' >> /var/www/html/config/config.inc.php && \
    echo '$config["smtp_user"] = "%u";' >> /var/www/html/config/config.inc.php && \
    echo '$config["smtp_pass"] = "%p";' >> /var/www/html/config/config.inc.php && \
    echo '$config["product_name"] = "KINAS GROUP Webmail";' >> /var/www/html/config/config.inc.php && \
    echo '$config["skin"] = "elastic";' >> /var/www/html/config/config.inc.php && \
    echo '$config["des_key"] = "lVRPVvUd3l5iYlmO1y8xGpuP";' >> /var/www/html/config/config.inc.php && \
    echo '$config["enable_installer"] = false;' >> /var/www/html/config/config.inc.php && \
    echo '$config["log_driver"] = "file";' >> /var/www/html/config/config.inc.php && \
    echo '$config["log_dir"] = "/var/www/html/logs/";' >> /var/www/html/config/config.inc.php && \
    echo '$config["auto_create_user"] = true;' >> /var/www/html/config/config.inc.php && \
    echo '$config["sent_mbox"] = "Sent";' >> /var/www/html/config/config.inc.php && \
    echo '$config["trash_mbox"] = "Trash";' >> /var/www/html/config/config.inc.php && \
    echo '$config["drafts_mbox"] = "Drafts";' >> /var/www/html/config/config.inc.php && \
    echo '$config["junk_mbox"] = "Junk";' >> /var/www/html/config/config.inc.php && \
    echo '$config["language"] = "en_US";' >> /var/www/html/config/config.inc.php && \
    echo '$config["timezone"] = "Africa/Lagos";' >> /var/www/html/config/config.inc.php && \
    echo '$config["enable_spellcheck"] = true;' >> /var/www/html/config/config.inc.php && \
    echo '$config["spellcheck_engine"] = "pspell";' >> /var/www/html/config/config.inc.php && \
    echo '?>' >> /var/www/html/config/config.inc.php

RUN chown -R www-data:www-data /var/www/html/config /var/www/html/logs /var/www/html/temp

EXPOSE 8080

CMD ["/start.sh"]
