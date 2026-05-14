FROM nginx:1.27-alpine

RUN rm /etc/nginx/conf.d/default.conf

COPY default.prod.conf /etc/nginx/conf.d/default.conf

# Create public directory for shared volume
RUN mkdir -p /var/www/html/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
