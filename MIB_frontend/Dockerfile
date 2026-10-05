# Use build arg to switch between dev and prod
ARG MODE=prod

# Step 1: Base build
FROM node:22 AS base

WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .

# ---------------------------------
# Development image
# ---------------------------------
FROM base AS dev

ARG MODE=dev

CMD ["npm", "run", "dev", "--", "--host"]

# ---------------------------------
# Production build image
# ---------------------------------
FROM base AS build
ARG MODE=prod
RUN npm run build

# ---------------------------------
# Production nginx server
# ---------------------------------
FROM nginx:alpine AS prod

# Remove default Nginx content
RUN rm -rf /usr/share/nginx/html/*

# Copy build output
COPY --from=build /app/dist /usr/share/nginx/html

# Optional: custom nginx config
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
