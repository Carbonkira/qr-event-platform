import { useCallback, useRef, useState } from 'react'
import { GoogleMap, useJsApiLoader, Marker, Autocomplete } from '@react-google-maps/api'
import { MapPin } from 'lucide-react'
import { Input } from '../ui'

const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY
const LIBRARIES = ['places']
const containerStyle = { width: '100%', height: '220px', borderRadius: '1rem' }
const DEFAULT_CENTER = { lat: 14.5995, lng: 120.9842 } // Metro Manila

// Venue/location picker used on the event create & edit forms. If no
// VITE_GOOGLE_MAPS_API_KEY is configured, falls back to the plain text
// inputs the app always had — the form keeps working with no setup.
export default function LocationPicker({ venue, location, lat, lng, onChange }) {
  if (!GOOGLE_MAPS_API_KEY) {
    return (
      <div className="grid sm:grid-cols-2 gap-3">
        <Input label="Venue" value={venue} onChange={e => onChange({ venue: e.target.value, location, lat, lng })} placeholder="e.g. Main Auditorium" required />
        <Input label="Location / City" value={location} onChange={e => onChange({ venue, location: e.target.value, lat, lng })} placeholder="e.g. Taguig City, Metro Manila" required />
      </div>
    )
  }
  return (
    <LocationPickerWithMap venue={venue} location={location} lat={lat} lng={lng} onChange={onChange} />
  )
}

function LocationPickerWithMap({ venue, location, lat, lng, onChange }) {
  const { isLoaded } = useJsApiLoader({ googleMapsApiKey: GOOGLE_MAPS_API_KEY, libraries: LIBRARIES })
  const [autocomplete, setAutocomplete] = useState(null)
  const inputRef = useRef(null)
  const center = lat && lng ? { lat: Number(lat), lng: Number(lng) } : DEFAULT_CENTER

  const onPlaceChanged = useCallback(() => {
    if (!autocomplete) return
    const place = autocomplete.getPlace()
    if (!place.geometry) return
    const newLat = place.geometry.location.lat()
    const newLng = place.geometry.location.lng()
    const parts = place.formatted_address?.split(',') || []
    onChange({
      venue: place.name || venue,
      location: parts.slice(1).join(',').trim() || location,
      lat: newLat,
      lng: newLng,
    })
  }, [autocomplete, onChange, venue, location])

  const onMarkerDragEnd = useCallback((e) => {
    onChange({ venue, location, lat: e.latLng.lat(), lng: e.latLng.lng() })
  }, [onChange, venue, location])

  if (!isLoaded) {
    return <div className="rounded-2xl bg-slate-100 animate-pulse" style={{ height: 220 }} />
  }

  return (
    <div className="space-y-3">
      <div>
        <label className="block text-[12px] font-semibold text-slate-600 mb-1.5">Search for a venue or address</label>
        <Autocomplete onLoad={setAutocomplete} onPlaceChanged={onPlaceChanged}>
          <div className="relative">
            <MapPin size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              ref={inputRef}
              defaultValue={venue}
              placeholder="Search for a place…"
              className="w-full rounded-xl border border-slate-200 bg-white text-sm px-3.5 py-2.5 pl-9 outline-none focus:border-[#1a1a2e] focus:ring-2 focus:ring-slate-100 transition-all"
            />
          </div>
        </Autocomplete>
      </div>

      <GoogleMap mapContainerStyle={containerStyle} center={center} zoom={lat && lng ? 15 : 11}>
        {lat && lng && <Marker position={{ lat: Number(lat), lng: Number(lng) }} draggable onDragEnd={onMarkerDragEnd} />}
      </GoogleMap>

      <div className="grid sm:grid-cols-2 gap-3">
        <Input label="Venue" value={venue} onChange={e => onChange({ venue: e.target.value, location, lat, lng })} placeholder="e.g. Main Auditorium" required />
        <Input label="Location / City" value={location} onChange={e => onChange({ venue, location: e.target.value, lat, lng })} placeholder="e.g. Taguig City, Metro Manila" required />
      </div>
    </div>
  )
}
