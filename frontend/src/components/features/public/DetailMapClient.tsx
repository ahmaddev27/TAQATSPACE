"use client";

import dynamic from "next/dynamic";
import { ImgPlaceholder } from "@/components/ui/ImgPlaceholder";

// Leaflet touches `window`; dynamic import with ssr:false must live in a client module.
const DetailMap = dynamic(() => import("./DetailMap").then((m) => m.DetailMap), {
  ssr: false,
  loading: () => <ImgPlaceholder label="" color="#dde6ec" h={240} radius="var(--r-lg)" />,
});

export interface DetailMapClientProps {
  lat: number;
  lng: number;
  label: string;
}

export function DetailMapClient(props: DetailMapClientProps) {
  return <DetailMap {...props} />;
}
