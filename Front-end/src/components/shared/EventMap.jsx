import { GoogleMap, useJsApiLoader, Marker } from '@react-google-maps/api'

const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY
const containerStyle = { width: '100%', height: '100%' }

// Read-only map shown on the event detail page. Falls back to the free
// no-API-key Google Maps iframe embed when there's no API key configured
// or the event has no saved lat/lng yet — the app upgrades automatically
// once VITE_GOOGLE_MAPS_API_KEY and a location are both set.
export default function EventMap({ event }) {
  const hasCoords = event.lat != null && event.lng != null

  if (!GOOGLE_MAPS_API_KEY || !hasCoords) {
    return (
      <iframe
        className="w-full h-full"
        frameBorder="0"
        scrolling="no"
        marginHeight="0"
        marginWidth="0"
        title="Event location"
        src={`https://maps.google.com/maps?q=${encodeURIComponent(event.venue + ', ' + event.location)}&t=&z=14&ie=UTF8&iwloc=&output=embed`}
      />
    )
  }
  return <InteractiveEventMap event={event} />
}

function InteractiveEventMap({ event }) {
  const { isLoaded } = useJsApiLoader({ googleMapsApiKey: GOOGLE_MAPS_API_KEY })
  const center = { lat: Number(event.lat), lng: Number(event.lng) }
  if (!isLoaded) return <div className="w-full h-full bg-slate-100 animate-pulse" />
  return (
    <GoogleMap mapContainerStyle={containerStyle} center={center} zoom={15}>
      <Marker position={center} />
    </GoogleMap>
  )
}
