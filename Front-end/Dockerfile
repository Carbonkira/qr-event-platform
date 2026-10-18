# Multi-stage: compile the Vite/React app, then serve the static output
# with nginx. Vite only reads import.meta.env.VITE_* at `vite build` time,
# not at container runtime - so the API URL has to be a build ARG, not a
# plain ENV the way the backend's config is. Changing it means rebuilding
# this image, not just restarting the container.
FROM node:20-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

ARG VITE_API_URL=http://localhost:8000/api
ARG VITE_GOOGLE_MAPS_API_KEY=
ENV VITE_API_URL=$VITE_API_URL
ENV VITE_GOOGLE_MAPS_API_KEY=$VITE_GOOGLE_MAPS_API_KEY

RUN npm run build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
