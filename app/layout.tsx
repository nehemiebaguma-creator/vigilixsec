import type { Metadata } from 'next'
import { SpeedInsights } from '@vercel/speed-insights/next'
import './globals.css'

export const metadata: Metadata = { title: 'VigilixSec — Centre de contrôle', description: 'Plateforme de sécurité unifiée VigilixSec' }
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="fr" className="bg-[#0b1115]"><body>{children}<SpeedInsights /></body></html> }
