FROM nginx:1.27-alpine AS production

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
RUN ln -snf /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
