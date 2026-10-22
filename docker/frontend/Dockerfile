FROM node:22-alpine

RUN npm install --global npm@11.19.1

WORKDIR /var/www/frontend

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
