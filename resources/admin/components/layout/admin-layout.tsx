import { type ReactNode } from 'react'
import { getCookie } from '@/lib/cookies'
import { cn } from '@/lib/utils'
import { DirectionProvider } from '@/context/direction-provider'
import { FontProvider } from '@/context/font-provider'
import { LayoutProvider } from '@/context/layout-provider'
import { SearchProvider } from '@/context/search-provider'
import { ThemeProvider } from '@/context/theme-provider'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { Toaster } from '@/components/ui/sonner'
import { AppSidebar } from '@/components/layout/app-sidebar'
import { Footer } from '@/components/layout/footer'
import { FlashToaster } from '@/components/flash-toaster'
import { NavigationProgress } from '@/components/navigation-progress'
import { SkipToMain } from '@/components/skip-to-main'

export function AdminLayout({ children }: { children: ReactNode }) {
  const defaultOpen = getCookie('sidebar_state') !== 'false'

  return (
    <ThemeProvider>
      <FontProvider>
        <DirectionProvider>
          <NavigationProgress />
          <SearchProvider>
            <LayoutProvider>
              <SidebarProvider defaultOpen={defaultOpen}>
                <SkipToMain />
                <AppSidebar />
                <SidebarInset
                  className={cn(
                    // Set content container, so we can use container queries
                    '@container/content',

                    // If layout is fixed, set the height
                    // to 100svh to prevent overflow
                    'has-data-[layout=fixed]:h-svh',

                    // If layout is fixed and sidebar is inset,
                    // set the height to 100svh - spacing (total margins) to prevent overflow
                    'peer-data-[variant=inset]:has-data-[layout=fixed]:h-[calc(100svh-(var(--spacing)*4))]'
                  )}
                >
                  <div className='flex min-h-0 flex-1 flex-col'>{children}</div>
                  <Footer />
                </SidebarInset>
              </SidebarProvider>
            </LayoutProvider>
          </SearchProvider>
          <Toaster duration={5000} />
          <FlashToaster />
        </DirectionProvider>
      </FontProvider>
    </ThemeProvider>
  )
}
