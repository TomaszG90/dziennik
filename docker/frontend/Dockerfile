FROM node:20-alpine

WORKDIR /var/www/frontend

EXPOSE 5173

# Główna komenda uruchamiana jest z poziomu docker-compose
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
