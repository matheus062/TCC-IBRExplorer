FROM node:20 AS frontend-build

WORKDIR /app

COPY app/package*.json ./

RUN npm install

COPY app .

RUN npm run build


FROM nginx:latest

RUN rm /etc/nginx/conf.d/default.conf

COPY docker/nginx/dev.conf.template /etc/nginx/templates/default.conf.template

COPY --from=frontend-build /app/dist /var/www/app/dist