/**
 * The sidebar nav links to `/owner/requests`; the canonical booking-requests
 * screen lives at `/owner/bookings`. This thin wrapper renders the same page so
 * a single implementation backs both routes.
 */
import OwnerBookingsPage from "../bookings/page";

export const dynamic = "force-dynamic";

export default OwnerBookingsPage;
