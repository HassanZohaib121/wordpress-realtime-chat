import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'v0 wordpress-realtime-chat',
  description: 'Created By Hassan Zohaib',
  generator: 'https://techpulseukltd.com',
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  )
}
